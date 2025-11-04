<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecCompanyLookupService
{
    /**
     * SEC API base URL
     * Note: Submissions API uses data.sec.gov, not www.sec.gov
     */
    private const SEC_BASE_URL = 'https://data.sec.gov';

    /**
     * Company tickers JSON URL
     */
    private const TICKERS_URL = 'https://www.sec.gov/files/company_tickers.json';

    /**
     * Get User-Agent header for SEC API requests
     * SEC requires a User-Agent header with contact info
     * 
     * Reference: https://www.sec.gov/developer/user-agents
     * Similar to edgartools: https://edgartools.readthedocs.io/en/latest/
     *
     * @return string
     */
    private function getUserAgent(): string
    {
        // Read from config (which reads from .env)
        // This works even when config is cached
        $contact = config('sec.api_contact', env('SEC_API_CONTACT', 'info@caitong.com'));
        $appName = config('app.name', 'CAITong');

        return "{$appName}/1.0 (contact: {$contact})";
    }

    /**
     * Oil & Gas SIC Code Ranges
     * Based on Standard Industrial Classification codes for oil and gas industry
     * Format: [start, end] for ranges, or [code, code] for single codes
     */
    private const OIL_GAS_SIC_RANGES = [
        [1300, 1389],  // Crude Petroleum and Natural Gas (all codes in range)
        [2911, 2911],  // Petroleum Refining
        [2990, 2999],  // Petroleum and Coal Products, Not Elsewhere Classified
        [4612, 4613],  // Crude and Refined Petroleum Pipelines
        [4922, 4925],  // Natural Gas Transmission and Distribution
        [5172, 5172],  // Petroleum Bulk Stations and Terminals
    ];

    /**
     * Get company details from ticker symbol
     *
     * @param  string  $ticker
     * @return array
     * @throws \Exception
     */
    public function getCompanyDetailsByTicker(string $ticker): array
    {
        try {
            // Step 1: Ticker → CIK Lookup
            $cik = $this->getCikFromTicker($ticker);

            if (!$cik) {
                throw new \Exception("Ticker symbol '{$ticker}' not found in SEC database");
            }

            // Step 2: CIK → Company Metadata Lookup
            $metadata = $this->getCompanyMetadataFromCik($cik);

            if (!$metadata) {
                throw new \Exception("Company metadata not found for CIK: {$cik}");
            }

            // Step 3: Oil & Gas Industry Validation
            $isOilGas = $this->validateOilGasIndustry($metadata);

            // Format response
            return [
                'company_name' => $metadata['name'] ?? null,
                'ticker_symbol' => strtoupper($ticker),
                'sec_cik_number' => $this->formatCik($cik),
                'sic_code' => $metadata['sic'] ?? null,
                'sic_description' => $this->getSicDescription($metadata['sic'] ?? null),
                'entity_type' => $metadata['entityType'] ?? null,
                'exchanges' => $metadata['exchanges'] ?? [],
                'state_of_incorporation' => $metadata['stateOfIncorporation'] ?? null,
                'is_oil_gas_company' => $isOilGas,
                'validation_status' => $isOilGas ? '✓ Valid Oil & Gas Company' : '✗ Not Oil & Gas Company',
            ];
        } catch (\Exception $e) {
            Log::error('SEC Company Lookup Error: '.$e->getMessage(), [
                'ticker' => $ticker,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get all companies from SEC
     * Returns list of all companies with their ticker symbols and basic info
     *
     * @param  bool  $oilGasOnly  Filter to only return oil & gas companies
     * @param  int|null  $limit  Limit number of results (null = all)
     * @param  int  $offset  Offset for pagination
     * @return array
     * @throws \Exception
     */
    public function getAllCompanies(bool $oilGasOnly = false, ?int $limit = null, int $offset = 0): array
    {
        try {
            // Get all tickers from SEC (cached for 24 hours)
            $cacheKey = 'sec_company_tickers';
            $tickers = Cache::remember($cacheKey, 86400, function () {
                try {
                    $response = Http::withOptions([
                        'verify' => true,
                        'timeout' => 30,
                    ])->withHeaders([
                        'User-Agent' => $this->getUserAgent(),
                    ])->timeout(30)->get(self::TICKERS_URL);

                    if ($response->successful()) {
                        return $response->json();
                    }

                    $errorMsg = 'Failed to fetch SEC company tickers. Status: '.$response->status();
                    if ($response->body()) {
                        $errorMsg .= ' Response: '.substr($response->body(), 0, 200);
                    }
                    throw new \Exception($errorMsg);
                } catch (\Exception $e) {
                    Log::error('Failed to fetch SEC company tickers: '.$e->getMessage(), [
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            });

            if (!$tickers || !is_array($tickers)) {
                throw new \Exception('Invalid tickers data from SEC');
            }

            $companies = [];
            $processed = 0;
            $skipped = 0;
            $checked = 0; // Total companies checked

            foreach ($tickers as $entry) {
                $checked++;
                // Skip if no ticker or CIK
                if (empty($entry['ticker']) || empty($entry['cik_str'])) {
                    $skipped++;
                    continue;
                }

                $ticker = strtoupper($entry['ticker']);
                $cik = (string) $entry['cik_str'];
                $companyName = $entry['title'] ?? null;

                // If filtering for oil & gas only, we need to fetch metadata
                if ($oilGasOnly) {
                    try {
                        $metadata = $this->getCompanyMetadataFromCik($cik);
                        if (!$metadata) {
                            $skipped++;
                            continue;
                        }

                        // Validate if it's an oil & gas company
                        if (!$this->validateOilGasIndustry($metadata)) {
                            $skipped++;
                            continue;
                        }

                        $companies[] = [
                            'company_name' => $metadata['name'] ?? $companyName,
                            'ticker_symbol' => $ticker,
                            'sec_cik_number' => $this->formatCik($cik),
                            'sic_code' => $metadata['sic'] ?? null,
                            'sic_description' => $this->getSicDescription($metadata['sic'] ?? null),
                            'entity_type' => $metadata['entityType'] ?? null,
                            'exchanges' => $metadata['exchanges'] ?? [],
                            'state_of_incorporation' => $metadata['stateOfIncorporation'] ?? null,
                            'is_oil_gas_company' => true,
                        ];
                        $processed++;

                        // Apply limit if specified
                        if ($limit !== null && $processed >= $limit) {
                            break;
                        }

                        // Add delay to avoid rate limiting (SEC allows ~10 requests per second)
                        // We use 0.12 seconds (120ms) to be safe, allowing ~8 requests/second
                        usleep(120000); // 0.12 second delay between each metadata request
                    } catch (\Exception $e) {
                        // Skip companies where metadata fetch fails
                        Log::debug('Skipped company due to metadata error: '.$e->getMessage(), [
                            'ticker' => $ticker,
                            'cik' => $cik,
                        ]);
                        $skipped++;
                        continue;
                    }
                } else {
                    // Return all companies with basic info
                    $companies[] = [
                        'company_name' => $companyName,
                        'ticker_symbol' => $ticker,
                        'sec_cik_number' => $this->formatCik($cik),
                    ];
                    $processed++;

                    // Apply limit if specified
                    if ($limit !== null && $processed >= $limit) {
                        break;
                    }
                }
            }

            return [
                'total_found' => $processed,
                'total_checked' => $checked,
                'total_skipped' => $skipped,
                'oil_gas_only' => $oilGasOnly,
                'limit' => $limit,
                'offset' => $offset,
                'companies' => array_slice($companies, $offset),
            ];
        } catch (\Exception $e) {
            Log::error('SEC Get All Companies Error: '.$e->getMessage(), [
                'oil_gas_only' => $oilGasOnly,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Step 1: Get CIK number from ticker symbol
     *
     * @param  string  $ticker
     * @return string|null
     */
    private function getCikFromTicker(string $ticker): ?string
    {
        $ticker = strtoupper($ticker);

        // Cache the tickers JSON for 24 hours
        $cacheKey = 'sec_company_tickers';
        $tickers = Cache::remember($cacheKey, 86400, function () {
            try {
                $response = Http::withOptions([
                    'verify' => true, // SSL verification
                    'timeout' => 30,
                ])->withHeaders([
                    'User-Agent' => $this->getUserAgent(),
                ])->timeout(30)->get(self::TICKERS_URL);

                if ($response->successful()) {
                    return $response->json();
                }

                $errorMsg = 'Failed to fetch SEC company tickers. Status: '.$response->status();
                if ($response->body()) {
                    $errorMsg .= ' Response: '.substr($response->body(), 0, 200);
                }
                throw new \Exception($errorMsg);
            } catch (\Exception $e) {
                Log::error('Failed to fetch SEC company tickers: '.$e->getMessage(), [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        });

        // Search for ticker in the JSON structure
        // SEC returns: {"0": {"cik_str": 1234567, "ticker": "AAPL", "title": "Company Name"}, ...}
        if ($tickers && is_array($tickers)) {
            foreach ($tickers as $entry) {
                if (isset($entry['ticker']) && strtoupper($entry['ticker']) === $ticker) {
                    return (string) $entry['cik_str'];
                }
            }
        }

        return null;
    }

    /**
     * Step 2: Get company metadata from CIK number
     *
     * @param  string  $cik
     * @return array|null
     */
    public function getCompanyMetadataFromCik(string $cik): ?array
    {
        $formattedCik = $this->formatCik($cik);
        $url = self::SEC_BASE_URL."/submissions/CIK{$formattedCik}.json";

        // Cache individual company metadata for 1 hour
        $cacheKey = "sec_company_metadata_cik_{$cik}";

        return Cache::remember($cacheKey, 3600, function () use ($url) {
            try {
                $response = Http::withOptions([
                    'verify' => true, // SSL verification
                    'timeout' => 30,
                ])->withHeaders([
                    'User-Agent' => $this->getUserAgent(),
                ])->timeout(30)->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // SEC submissions API returns data in this structure
                    // The SIC code can be a string, integer, or nested structure
                    $sicCode = null;
                    if (isset($data['sic'])) {
                        if (is_array($data['sic'])) {
                            // Sometimes it's an array, get first element or 'code' field
                            $sicCode = $data['sic']['code'] ?? ($data['sic'][0] ?? null);
                        } else {
                            // Direct value (string or int)
                            $sicCode = $data['sic'];
                        }
                    }
                    
                    // Extract relevant metadata from SEC submissions response
                    return [
                        'name' => $data['name'] ?? null,
                        'cik' => $data['cik'] ?? null,
                        'sic' => $sicCode,
                        'entityType' => $data['entityType'] ?? null,
                        'exchanges' => $data['exchanges'] ?? [],
                        'stateOfIncorporation' => $data['stateOfIncorporation'] ?? null,
                        'fiscalYearEnd' => $data['fiscalYearEnd'] ?? null,
                    ];
                }

                $errorMsg = 'Failed to fetch company metadata from SEC. Status: '.$response->status();
                if ($response->body()) {
                    $errorMsg .= ' Response: '.substr($response->body(), 0, 200);
                }
                throw new \Exception($errorMsg);
            } catch (\Exception $e) {
                Log::error('Failed to fetch SEC company metadata: '.$e->getMessage(), [
                    'url' => $url,
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Step 3: Validate if company is in Oil & Gas industry
     * Checks if SIC code falls within any of the defined ranges
     *
     * @param  array  $metadata
     * @return bool
     */
    public function validateOilGasIndustry(array $metadata): bool
    {
        $sic = $metadata['sic'] ?? null;

        if (!$sic) {
            return false;
        }

        // SIC code might be a string like "1311" or integer
        $sicCode = is_numeric($sic) ? (int) $sic : null;

        if ($sicCode === null) {
            return false;
        }

        // Check if SIC code falls within any of the defined ranges
        foreach (self::OIL_GAS_SIC_RANGES as $range) {
            [$start, $end] = $range;
            if ($sicCode >= $start && $sicCode <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format CIK number to 10 digits with leading zeros
     *
     * @param  string  $cik
     * @return string
     */
    private function formatCik(string $cik): string
    {
        return str_pad((string) $cik, 10, '0', STR_PAD_LEFT);
    }

    /**
     * Get SIC description from code
     * This is a basic mapping - you may want to expand this or use an external source
     *
     * @param  string|int|null  $sicCode
     * @return string|null
     */
    public function getSicDescription($sicCode): ?string
    {
        if (!$sicCode) {
            return null;
        }

        $sicCode = (int) $sicCode;

        // Check if it's in oil & gas range first
        $isOilGas = $this->validateOilGasIndustry(['sic' => $sicCode]);
        if (!$isOilGas) {
            return null;
        }

        // Specific descriptions for common codes
        $descriptions = [
            1311 => 'Crude Petroleum and Natural Gas',
            1381 => 'Drilling Oil and Gas Wells',
            1382 => 'Oil and Gas Field Exploration Services',
            1389 => 'Oil and Gas Field Services, Not Elsewhere Classified',
            2911 => 'Petroleum Refining',
            2990 => 'Petroleum and Coal Products (General)',
            2999 => 'Petroleum and Coal Products, Not Elsewhere Classified',
            4612 => 'Crude Petroleum Pipelines',
            4613 => 'Refined Petroleum Pipelines',
            4922 => 'Natural Gas Transmission',
            4923 => 'Natural Gas Transmission and Distribution',
            4924 => 'Natural Gas Distribution',
            4925 => 'Mixed, Manufactured, or Liquefied Petroleum Gas Production and/or Distribution',
            5172 => 'Petroleum Bulk Stations and Terminals',
        ];

        // If we have a specific description, return it
        if (isset($descriptions[$sicCode])) {
            return $descriptions[$sicCode];
        }

        // Otherwise, return a generic description based on range
        if ($sicCode >= 1300 && $sicCode <= 1389) {
            return 'Crude Petroleum and Natural Gas (Range)';
        }
        if ($sicCode >= 2990 && $sicCode <= 2999) {
            return 'Petroleum and Coal Products (Range)';
        }
        if ($sicCode >= 4922 && $sicCode <= 4925) {
            return 'Natural Gas Transmission and Distribution (Range)';
        }

        return 'Oil & Gas Industry';
    }
}

