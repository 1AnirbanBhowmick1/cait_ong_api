<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\SecCompanyLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies (search from database).
     * This searches the local database for companies loaded from SEC.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'search' => 'nullable|string|max:255',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Invalid parameters',
                    'messages' => $validator->errors(),
                ], 400);
            }

            // Get validated parameters with defaults
            $search = $request->input('search');
            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);

            // Build query - only basic fields needed for search results
            $query = Company::query()
                ->select([
                    'company_id',
                    'company_name',
                    'ticker_symbol',
                    'sec_cik_number',
                    'sic_description',
                    'extraction_flag'
                ]);

            // Apply search filter (by company name or ticker)
            if ($search) {
                $query->search($search);
            }

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination and ordering
            $companies = $query
                ->orderBy('company_name', 'asc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            // return response()->json($companies->toArray(), 200);

            $result = [
                'meta' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total,
                ],
                'data' => $companies->map(function ($company) {
                    return [
                        'company_id' => $company->company_id,
                        'company_name' => $company->company_name,
                        'ticker_symbol' => $company->ticker_symbol,
                        'sec_cik_number' => $company->sec_cik_number,
                        'sic_description' => $company->sic_description,
                        'has_details' => (bool) $company->extraction_flag, // Indicates if details have been extracted
                    ];
                }),
            ];

            return response()->json($result, 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Companies API Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get company details from SEC by ticker symbol
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $ticker
     * @return \Illuminate\Http\JsonResponse
     */
    public function lookupByTicker(Request $request, string $ticker)
    {
        try {
            // Validate ticker parameter
            $validator = Validator::make(['ticker' => $ticker], [
                'ticker' => 'required|string|max:10|regex:/^[A-Z0-9]+$/i',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Invalid ticker symbol',
                    'messages' => $validator->errors(),
                ], 400);
            }

            // Initialize service and lookup company
            $lookupService = new SecCompanyLookupService();
            $companyDetails = $lookupService->getCompanyDetailsByTicker($ticker);

            return response()->json([
                'success' => true,
                'data' => $companyDetails,
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Ticker Lookup API Error: '.$e->getMessage(), [
                'ticker' => $ticker,
                'trace' => $e->getTraceAsString(),
            ]);

            // Determine appropriate status code based on error
            $statusCode = 500;
            $errorMessage = 'Internal server error';

            if (strpos($e->getMessage(), 'not found') !== false) {
                $statusCode = 404;
                $errorMessage = $e->getMessage();
            } elseif (strpos($e->getMessage(), 'Failed to fetch') !== false) {
                $statusCode = 503;
                $errorMessage = 'SEC API temporarily unavailable';
            }

            return response()->json([
                'success' => false,
                'error' => $errorMessage,
                'ticker' => $ticker,
            ], $statusCode);
        }
    }

    /**
     * Get detailed company information when user clicks on a company.
     * This will:
     * 1. Fetch details from SEC API if not already extracted
     * 2. Check if it's an oil & gas company
     * 3. Save details to database
     * 4. Return details or alert if not oil & gas
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  Company ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCompanyDetails(Request $request, int $id)
    {
        try {
            // Find company in database
            $company = Company::find($id);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'error' => 'Company not found',
                ], 404);
            }

            // If already extracted (has sic_code populated), return from database
            if (!empty($company->sic_code)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'company_id' => $company->company_id,
                        'company_name' => $company->company_name,
                        'ticker_symbol' => $company->ticker_symbol,
                        'sec_cik_number' => $company->sec_cik_number,
                        'sic_code' => $company->sic_code,
                        'sic_description' => $company->sic_description,
                        'entity_type' => $company->entity_type,
                        'is_oil_gas_company' => $company->isOilGasCompany(),
                        'extraction_flag' => $company->extraction_flag,
                        'admin_approval_flag' => $company->admin_approval_flag,
                    ],
                    'cached' => true,
                ], 200);
            }

            // Fetch details from SEC API
            $lookupService = new SecCompanyLookupService();
            $ticker = $company->ticker_symbol;
            
            if (!$ticker) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ticker symbol not found for this company',
                ], 400);
            }

            $secDetails = $lookupService->getCompanyDetailsByTicker($ticker);

            // Check if it's an oil & gas company
            $isOilGas = $secDetails['is_oil_gas_company'] ?? false;

            if (!$isOilGas) {
                // Return alert - not an oil & gas company
                return response()->json([
                    'success' => false,
                    'is_oil_gas_company' => false,
                    'message' => 'This company is not an oil & gas company',
                    'company_name' => $company->company_name,
                    'ticker_symbol' => $company->ticker_symbol,
                ], 200);
            }

            // Update company with extracted details (keep extraction_flag as false by default)
            $company->update([
                'sic_code' => $secDetails['sic_code'] ?? null,
                'sic_description' => $secDetails['sic_description'] ?? null,
                'entity_type' => $secDetails['entity_type'] ?? null,
                // extraction_flag remains false by default
            ]);

            return response()->json([
                'success' => true,
                'is_oil_gas_company' => true,
                'data' => [
                    'company_id' => $company->company_id,
                    'company_name' => $company->company_name,
                    'ticker_symbol' => $company->ticker_symbol,
                    'sec_cik_number' => $company->sec_cik_number,
                    'sic_code' => $company->sic_code,
                    'sic_description' => $company->sic_description,
                    'entity_type' => $company->entity_type,
                    'is_oil_gas_company' => true,
                    'extraction_flag' => $company->extraction_flag,
                ],
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Get Company Details API Error: '.$e->getMessage(), [
                'company_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            // Determine appropriate status code based on error
            $statusCode = 500;
            $errorMessage = 'Internal server error';

            if (strpos($e->getMessage(), 'not found') !== false) {
                $statusCode = 404;
                $errorMessage = $e->getMessage();
            } elseif (strpos($e->getMessage(), 'Failed to fetch') !== false) {
                $statusCode = 503;
                $errorMessage = 'SEC API temporarily unavailable';
            }

            return response()->json([
                'success' => false,
                'error' => $errorMessage,
            ], $statusCode);
        }
    }

    /**
     * Get all companies from SEC database
     * Can filter to show only oil & gas companies
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllCompaniesFromSec(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'oil_gas_only' => 'nullable|string|in:true,false,1,0',
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Invalid parameters',
                    'messages' => $validator->errors(),
                ], 400);
            }

            // Get validated parameters with defaults and convert to proper types
            $oilGasOnlyInput = $request->input('oil_gas_only', 'false');
            // Convert string "true"/"1" to boolean true, everything else to false
            $oilGasOnly = in_array(strtolower($oilGasOnlyInput), ['true', '1'], true);
            
            // For oil & gas filtering, set a default limit to avoid timeout/rate limits
            // Processing all companies could take hours due to API rate limits
            // Default limit is smaller for oil & gas to avoid timeout issues
            $defaultLimit = $oilGasOnly ? 20 : null;
            $limit = $request->has('limit') ? (int) $request->input('limit') : $defaultLimit;
            $offset = (int) $request->input('offset', 0);
            
            // Warn user if requesting too many with oil_gas_only
            if ($oilGasOnly && $limit > 100) {
                \Log::warning('Large oil & gas filter request', [
                    'limit' => $limit,
                    'estimated_time_seconds' => $limit * 0.5, // Rough estimate: ~0.5s per company
                ]);
            }

            // Initialize service and get all companies
            $lookupService = new SecCompanyLookupService();
            $result = $lookupService->getAllCompanies($oilGasOnly, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Get All Companies From SEC API Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // Determine appropriate status code based on error
            $statusCode = 500;
            $errorMessage = 'Internal server error';

            if (strpos($e->getMessage(), 'Failed to fetch') !== false) {
                $statusCode = 503;
                $errorMessage = 'SEC API temporarily unavailable';
            }

            return response()->json([
                'success' => false,
                'error' => $errorMessage,
            ], $statusCode);
        }
    }

    /**
     * Request approval for a company
     * Sets admin_approval_flag to "pending" when user clicks request button
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  Company ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestApproval(Request $request, int $id)
    {
        try {
            // Find company in database
            $company = Company::find($id);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'error' => 'Company not found',
                ], 404);
            }

            // Check if company details have been extracted (has sic_code populated)
            if (empty($company->sic_code)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Company details not extracted yet. Please extract details first.',
                ], 400);
            }

            // Check if company is actually an oil & gas company
            if (!$company->isOilGasCompany()) {
                return response()->json([
                    'success' => false,
                    'error' => 'This company is not an oil & gas company',
                    'is_oil_gas_company' => false,
                ], 400);
            }

            // Update admin_approval_flag to "PENDING" (must be uppercase per database constraint)
            $company->update([
                'admin_approval_flag' => 'PENDING',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Approval request submitted successfully',
                'data' => [
                    'company_id' => $company->company_id,
                    'company_name' => $company->company_name,
                    'ticker_symbol' => $company->ticker_symbol,
                    'admin_approval_flag' => $company->admin_approval_flag,
                ],
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Request Approval API Error: '.$e->getMessage(), [
                'company_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Approve a company (Admin only)
     * Changes admin_approval_flag from "PENDING" to "APPROVED"
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  Company ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveCompany(Request $request, int $id)
    {
        try {
            // Find company in database
            $company = Company::find($id);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'error' => 'Company not found',
                ], 404);
            }

            // Check if company is in PENDING status
            if ($company->admin_approval_flag !== 'PENDING') {
                return response()->json([
                    'success' => false,
                    'error' => 'Company approval status must be PENDING to approve. Current status: '.($company->admin_approval_flag ?? 'NULL'),
                    'current_status' => $company->admin_approval_flag,
                ], 400);
            }

            // Update admin_approval_flag to "APPROVED"
            $company->update([
                'admin_approval_flag' => 'APPROVED',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Company approved successfully',
                'data' => [
                    'company_id' => $company->company_id,
                    'company_name' => $company->company_name,
                    'ticker_symbol' => $company->ticker_symbol,
                    'admin_approval_flag' => $company->admin_approval_flag,
                ],
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Approve Company API Error: '.$e->getMessage(), [
                'company_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Reject a company (Admin only)
     * Changes admin_approval_flag from "PENDING" to "REJECTED"
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  Company ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectCompany(Request $request, int $id)
    {
        try {
            // Find company in database
            $company = Company::find($id);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'error' => 'Company not found',
                ], 404);
            }

            // Check if company is in PENDING status
            if ($company->admin_approval_flag !== 'PENDING') {
                return response()->json([
                    'success' => false,
                    'error' => 'Company approval status must be PENDING to reject. Current status: '.($company->admin_approval_flag ?? 'NULL'),
                    'current_status' => $company->admin_approval_flag,
                ], 400);
            }

            // Update admin_approval_flag to "REJECTED"
            $company->update([
                'admin_approval_flag' => 'REJECTED',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Company rejected successfully',
                'data' => [
                    'company_id' => $company->company_id,
                    'company_name' => $company->company_name,
                    'ticker_symbol' => $company->ticker_symbol,
                    'admin_approval_flag' => $company->admin_approval_flag,
                ],
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Reject Company API Error: '.$e->getMessage(), [
                'company_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
