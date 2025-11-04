<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\SecCompanyLookupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoadCompaniesFromSec extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'companies:load-from-sec 
                            {--limit= : Limit number of companies to load (for testing)}
                            {--oil-gas-only : Load only oil & gas companies with full details}
                            {--resume : Skip companies that already have sic_code populated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load companies from SEC into companies_v1 table. Use --oil-gas-only to load only oil & gas companies with full details.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to load companies from SEC...');

        try {
            $lookupService = new SecCompanyLookupService();
            $limit = $this->option('limit') ? (int) $this->option('limit') : null;
            $oilGasOnly = $this->option('oil-gas-only');
            $resume = $this->option('resume');

            if ($oilGasOnly) {
                return $this->loadOilGasCompanies($lookupService, $limit, $resume);
            }

            // Get all companies from SEC (without filtering) - only basic info
            $this->info('Fetching company list from SEC...');
            $result = $lookupService->getAllCompanies(false, $limit, 0);

            $companies = $result['companies'] ?? [];
            $totalCompanies = count($companies);

            if ($totalCompanies === 0) {
                $this->error('No companies found from SEC API');
                return 1;
            }

            $this->info("Found {$totalCompanies} companies to load (basic info only)");
            $this->newLine();

            $bar = $this->output->createProgressBar($totalCompanies);
            $bar->start();

            $loaded = 0;
            $skipped = 0;
            $errors = 0;

            // Process one company at a time
            foreach ($companies as $companyData) {
                try {
                    // Check if company already exists
                    $existing = Company::where('ticker_symbol', $companyData['ticker_symbol'])
                        ->orWhere('sec_cik_number', $companyData['sec_cik_number'])
                        ->first();

                    if ($existing) {
                        // Update basic info if exists
                        $existing->update([
                            'company_name' => $companyData['company_name'],
                            'ticker_symbol' => $companyData['ticker_symbol'],
                            'sec_cik_number' => $companyData['sec_cik_number'],
                        ]);
                        $skipped++;
                    } else {
                        // Create new company with only basic info
                        // Note: sic_code and sic_description are required fields, so we set empty strings
                        Company::create([
                            'company_name' => $companyData['company_name'],
                            'ticker_symbol' => $companyData['ticker_symbol'],
                            'sec_cik_number' => $companyData['sec_cik_number'],
                            'sic_code' => '', // Empty until extracted
                            'sic_description' => '', // Empty until extracted
                            'extraction_flag' => false, // Not extracted yet
                        ]);
                        $loaded++;
                    }

                    $bar->advance();
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Error loading company: '.$e->getMessage(), [
                        'company' => $companyData,
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✓ Loaded: {$loaded} new companies");
            $this->info("✓ Updated: {$skipped} existing companies");
            if ($errors > 0) {
                $this->warn("⚠ Errors: {$errors} companies");
            }

            $this->newLine();
            $this->info('Companies loaded successfully!');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to load companies: '.$e->getMessage());
            Log::error('LoadCompaniesFromSec Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Load only oil & gas companies with full details
     * Processes one company at a time sequentially
     *
     * @param  SecCompanyLookupService  $lookupService
     * @param  int|null  $limit
     * @param  bool  $resume
     * @return int
     */
    private function loadOilGasCompanies($lookupService, $limit, $resume = false)
    {
        $this->info('Loading OIL & GAS companies only with FULL DETAILS...');
        $this->warn('Processing one company at a time (sequential).');
        
        if ($resume) {
            $this->info('Resume mode: Skipping companies that are already extracted.');
        }
        $this->newLine();

        try {
            // Get all companies with basic info first (fast - no API calls)
            // Don't limit here - we need to check many companies to find oil & gas ones
            $this->info('Fetching company list from SEC (basic info only)...');
            $this->warn('Note: We will check companies until we find oil & gas ones.');
            $this->newLine();
            
            $result = $lookupService->getAllCompanies(false, null, 0);
            $allCompanies = $result['companies'] ?? [];

            if (count($allCompanies) === 0) {
                $this->error('No companies found from SEC API');
                return 1;
            }

            $this->info("Found " . count($allCompanies) . " companies to check");
            $this->newLine();

            // Load all existing companies from DB once (to avoid repeated DB queries)
            // This includes both ticker symbols and CIK numbers for matching
            $this->info("Loading existing companies from database...");
            $existingTickers = Company::whereNotNull('ticker_symbol')
                ->where('sic_code', '!=', '')
                ->where('sic_code', '!=', null)
                ->pluck('ticker_symbol')
                ->toArray();
            
            $existingCiks = Company::whereNotNull('sec_cik_number')
                ->where('sic_code', '!=', '')
                ->where('sic_code', '!=', null)
                ->pluck('sec_cik_number')
                ->toArray();
            
            // Convert to arrays for faster lookup
            $existingTickersSet = array_flip($existingTickers);
            $existingCiksSet = array_flip($existingCiks);
            
            $initialCount = count($allCompanies);
            
            // Filter out companies that already exist (in-memory check, no DB queries)
            $allCompanies = array_filter($allCompanies, function($company) use ($existingTickersSet, $existingCiksSet) {
                $ticker = $company['ticker_symbol'] ?? null;
                $cik = $company['sec_cik_number'] ?? null;
                
                // Skip if ticker or CIK already exists in database
                if ($ticker && isset($existingTickersSet[$ticker])) {
                    return false;
                }
                if ($cik && isset($existingCiksSet[$cik])) {
                    return false;
                }
                return true;
            });
            
            $skippedCount = $initialCount - count($allCompanies);
            if ($skippedCount > 0) {
                $this->info("Skipping {$skippedCount} companies that already exist in database");
                $this->newLine();
            }

            if (count($allCompanies) === 0) {
                $this->info('All companies are already loaded!');
                return 0;
            }

            $this->info("Processing " . count($allCompanies) . " companies one at a time...");
            $this->warn("This will check each company to find oil & gas companies.");
            if ($limit !== null) {
                $this->info("Will stop after finding {$limit} oil & gas companies.");
            }
            $this->newLine();

            $loaded = 0;
            $updated = 0;
            $errors = 0;
            $notOilGas = 0;
            $checked = 0;

            // Process one company at a time until we find enough oil & gas companies
            foreach ($allCompanies as $companyData) {
                try {
                    $ticker = $companyData['ticker_symbol'] ?? null;
                    $cik = $companyData['sec_cik_number'] ?? null;
                    $companyName = $companyData['company_name'] ?? null;

                    if (!$ticker || !$cik) {
                        $errors++;
                        $checked++;
                        continue;
                    }

                    // Fetch metadata from SEC for this company
                    $metadata = $lookupService->getCompanyMetadataFromCik($cik);

                    if (!$metadata) {
                        $errors++;
                        $checked++;
                        continue;
                    }

                    // Check if it's an oil & gas company
                    $isOilGas = $lookupService->validateOilGasIndustry($metadata);

                    if (!$isOilGas) {
                        // Not an oil & gas company, skip it
                        $notOilGas++;
                        $checked++;
                        continue;
                    }

                    // Found an oil & gas company!
                    // Check if company already exists (only when we need to save)
                    // This should rarely happen since we filtered at the start, but check just in case
                    $existing = Company::where('ticker_symbol', $ticker)
                        ->orWhere('sec_cik_number', $cik)
                        ->first();

                    $companyDataToSave = [
                        'company_name' => $metadata['name'] ?? $companyName,
                        'ticker_symbol' => $ticker,
                        'sec_cik_number' => $cik,
                        'sic_code' => $metadata['sic'] ?? '',
                        'sic_description' => $lookupService->getSicDescription($metadata['sic'] ?? null) ?? '',
                        'entity_type' => $metadata['entityType'] ?? null,
                        'extraction_flag' => false, // Keep as false by default
                    ];

                    if ($existing) {
                        // Update with full details (shouldn't happen often due to filtering)
                        $existing->update($companyDataToSave);
                        $updated++;
                        $this->info("✓ Updated: {$companyName} ({$ticker})");
                    } else {
                        // Create new company with full details
                        Company::create($companyDataToSave);
                        $loaded++;
                        $this->info("✓ Loaded: {$companyName} ({$ticker})");
                    }

                    $checked++;

                    // Apply limit if specified and we've loaded enough
                    if ($limit !== null && ($loaded + $updated) >= $limit) {
                        $this->newLine();
                        $this->info("Reached limit of {$limit} oil & gas companies.");
                        break;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $checked++;
                    Log::error('Error loading oil & gas company: '.$e->getMessage(), [
                        'company' => $companyData,
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->newLine();
            $this->info("=========================================");
            $this->info("✓ Loaded: {$loaded} new oil & gas companies");
            $this->info("✓ Updated: {$updated} existing companies");
            $this->info("ℹ Checked: {$checked} companies total");
            $this->info("ℹ Skipped: {$notOilGas} non-oil & gas companies");
            if ($errors > 0) {
                $this->warn("⚠ Errors: {$errors} companies");
            }

            $this->newLine();
            $this->info('Oil & Gas companies loaded successfully with full details!');
            
            // Show total count (companies with sic_code populated, which means they've been processed)
            $totalInDb = Company::whereNotNull('sic_code')
                ->where('sic_code', '!=', '')
                ->count();
            $this->info("Total processed companies in database: {$totalInDb}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to load oil & gas companies: '.$e->getMessage());
            Log::error('LoadOilGasCompanies Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
