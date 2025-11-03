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
     */
    private const OIL_GAS_SIC_CODES = [
        // Crude Petroleum and Natural Gas
        1311,
        // Drilling Oil and Gas Wells
        1381,
        // Oil and Gas Field Exploration Services
        1382,
        // Oil and Gas Field Services, Not Elsewhere Classified
        1389,
        // Petroleum Refining
        2911,
        // Crude Petroleum Pipelines
        4612,
        // Refined Petroleum Pipelines
        4613,
        // Natural Gas Transmission
        4922,
        // Natural Gas Transmission and Distribution
        4923,
        // Natural Gas Distribution
        4924,
        // Mixed, Manufactured, or Liquefied Petroleum Gas Production and/or Distribution
        4925,
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
    private function getCompanyMetadataFromCik(string $cik): ?array
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
                    // Extract relevant metadata from SEC submissions response
                    return [
                        'name' => $data['name'] ?? null,
                        'cik' => $data['cik'] ?? null,
                        'sic' => isset($data['sic']) ? (is_array($data['sic']) ? ($data['sic'][0] ?? null) : $data['sic']) : null,
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
     *
     * @param  array  $metadata
     * @return bool
     */
    private function validateOilGasIndustry(array $metadata): bool
    {
        $sic = $metadata['sic'] ?? null;

        if (!$sic) {
            return false;
        }

        // SIC code might be a string like "1311" or integer
        $sicCode = is_numeric($sic) ? (int) $sic : null;

        return $sicCode !== null && in_array($sicCode, self::OIL_GAS_SIC_CODES, true);
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
    private function getSicDescription($sicCode): ?string
    {
        if (!$sicCode) {
            return null;
        }

        $sicCode = (int) $sicCode;

        $descriptions = [
            1311 => 'Crude Petroleum and Natural Gas',
            1381 => 'Drilling Oil and Gas Wells',
            1382 => 'Oil and Gas Field Exploration Services',
            1389 => 'Oil and Gas Field Services, Not Elsewhere Classified',
            2911 => 'Petroleum Refining',
            4612 => 'Crude Petroleum Pipelines',
            4613 => 'Refined Petroleum Pipelines',
            4922 => 'Natural Gas Transmission',
            4923 => 'Natural Gas Transmission and Distribution',
            4924 => 'Natural Gas Distribution',
            4925 => 'Mixed, Manufactured, or Liquefied Petroleum Gas Production and/or Distribution',
        ];

        return $descriptions[$sicCode] ?? null;
    }
}

