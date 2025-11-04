<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\SecCompanyLookupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOilGasCompanyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Company data to process
     *
     * @var array
     */
    public $companyData;

    /**
     * Create a new job instance.
     *
     * @param  array  $companyData  Company data with ticker, cik, name
     * @return void
     */
    public function __construct(array $companyData)
    {
        $this->companyData = $companyData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $lookupService = new SecCompanyLookupService();
            $ticker = $this->companyData['ticker_symbol'] ?? null;
            $cik = $this->companyData['sec_cik_number'] ?? null;
            $companyName = $this->companyData['company_name'] ?? null;

            if (!$ticker || !$cik) {
                Log::warning('ProcessOilGasCompanyJob: Missing ticker or CIK', [
                    'company_data' => $this->companyData,
                ]);
                return;
            }

            // Fetch metadata from SEC
            $metadata = $lookupService->getCompanyMetadataFromCik($cik);

            if (!$metadata) {
                Log::debug('ProcessOilGasCompanyJob: No metadata found', [
                    'ticker' => $ticker,
                    'cik' => $cik,
                ]);
                return;
            }

            // Validate if it's an oil & gas company
            $isOilGas = $lookupService->validateOilGasIndustry($metadata);

            if (!$isOilGas) {
                // Not an oil & gas company, skip it
                return;
            }

            // Check if company already exists
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
                // extraction_flag remains false by default
            ];

            if ($existing) {
                // Update with full details
                $existing->update($companyDataToSave);
            } else {
                // Create new company with full details
                Company::create($companyDataToSave);
            }
        } catch (\Exception $e) {
            Log::error('ProcessOilGasCompanyJob Error: '.$e->getMessage(), [
                'company_data' => $this->companyData,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('ProcessOilGasCompanyJob Failed: '.$exception->getMessage(), [
            'company_data' => $this->companyData,
            'exception' => get_class($exception),
        ]);
    }
}
