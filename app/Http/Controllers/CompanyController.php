<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\SecCompanyLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'active_only' => 'nullable|boolean',
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
            $activeOnly = $request->input('active_only', true);
            $search = $request->input('search');
            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);

            // Build query
            $query = Company::query()
                ->select([
                    'company_id',
                    'company_name',
                    'ticker_symbol',
                    'company_type',
                    'status',
                    'created_at',
                ]);

            // Apply active filter
            if ($activeOnly) {
                $query->active();
            }

            // Apply search filter
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
                        'company_type' => $company->company_type,
                        'status' => $company->status,
                        'created_at' => $company->created_at ? $company->created_at->toIso8601String() : null,
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
}
