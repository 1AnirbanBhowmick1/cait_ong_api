<?php

namespace App\Http\Controllers;

use App\Models\MetricValue;
use App\Services\UnitConverterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MetricsController extends Controller
{
    protected $unitConverter;
    
    public function __construct(UnitConverterService $unitConverter)
    {
        $this->unitConverter = $unitConverter;
    }
    
    /**
     * Display a listing of metrics for a company
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'company_id' => 'required|integer|min:1',
                'period_end_date' => 'nullable|date_format:Y-m-d',
                'period' => 'nullable|string|regex:/^\d{4}-Q[1-4]$/',
                'metric' => 'nullable|string',
                'basin' => 'nullable|string|max:100',
                'segment' => 'nullable|string|max:100',
                'asset_name' => 'nullable|string|max:100',
                'gross_or_net' => 'nullable|in:gross,net',
                'confidence_min' => 'nullable|numeric|min:0|max:1',
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
                'sort_by' => 'nullable|string|regex:/^[a-z_]+:(asc|desc)$/',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Invalid parameters',
                    'messages' => $validator->errors()
                ], 400);
            }

            // Get validated parameters
            $companyId = $request->input('company_id');
            $periodEndDate = $request->input('period_end_date');
            $period = $request->input('period');
            $metrics = $request->input('metric');
            $basin = $request->input('basin');
            $segment = $request->input('segment');
            $assetName = $request->input('asset_name');
            $grossOrNet = $request->input('gross_or_net');
            $confidenceMin = $request->input('confidence_min');
            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);
            $sortBy = $request->input('sort_by', 'period_end_date:desc');

            // Handle period parameter (convert Q4-2024 to date)
            if ($period && !$periodEndDate) {
                $periodEndDate = $this->convertPeriodToDate($period);
            }

            // Parse metrics (comma-separated)
            $metricsArray = null;
            if ($metrics) {
                $metricsArray = array_map('trim', explode(',', $metrics));
                
                // Validate metric names
                if (empty($metricsArray)) {
                    return response()->json([
                        'error' => 'Invalid metric parameter'
                    ], 422);
                }
            }

            // Build base query with joins
            $query = MetricValue::query()
                ->select([
                    'metric_value.metric_value_id',
                    'metric_value.company_id',
                    'companies.company_name',
                    'metric_value.metric_id',
                    'metric_definition.metric_name_internal',
                    'metric_definition.metric_name_display',
                    'metric_value.extracted_metric_value as original_value',
                    'metric_value.extracted_metric_unit as original_unit',
                    DB::raw('NULL as normalized_value'),
                    DB::raw('NULL as normalized_unit'),
                    'metric_value.period_start_date',
                    'metric_value.period_end_date',
                    'metric_value.basin_name',
                    'metric_value.segment_name',
                    DB::raw('NULL as asset_name'),
                    DB::raw('NULL as gross_or_net'),
                    'metric_value.extraction_confidence_score',
                    'metric_value.extraction_method',
                    'metric_value.source_document_id',
                    'source_document.source_url',
                    DB::raw('NULL as source_location'),
                    'metric_value.created_at'
                ])
                ->join('metric_definition', 'metric_value.metric_id', '=', 'metric_definition.metric_id')
                ->join('companies', 'metric_value.company_id', '=', 'companies.company_id')
                ->leftJoin('source_document', 'metric_value.source_document_id', '=', 'source_document.source_document_id');

            // Apply filters - use fully qualified column name
            $query->where('metric_value.company_id', $companyId);
            
            if ($periodEndDate) {
                $query->byPeriodEndDate($periodEndDate);
            }
            
            if ($metricsArray) {
                $query->whereIn('metric_definition.metric_name_internal', $metricsArray);
            }
            
            if ($basin) {
                $query->byBasin($basin);
            }
            
            if ($segment) {
                $query->bySegment($segment);
            }
            
            // Note: asset_name column doesn't exist in current schema
            // if ($assetName) {
            //     $query->byAsset($assetName);
            // }
            
            // Note: gross_or_net column doesn't exist in current schema
            // if ($grossOrNet) {
            //     $query->byGrossOrNet($grossOrNet);
            // }
            
            if ($confidenceMin !== null) {
                $query->byMinConfidence($confidenceMin);
            }

            // Get total count
            $total = $query->count();

            // Apply sorting
            [$sortField, $sortDirection] = $this->parseSortBy($sortBy);
            $query->orderBy($sortField, $sortDirection);

            // Apply pagination
            $results = $query
                ->limit($limit)
                ->offset($offset)
                ->get();

            // Process results with unit conversion
            $data = $results->map(function ($row) {
                // Perform unit conversion
                $conversion = $this->unitConverter->convert(
                    $row->original_value,
                    $row->original_unit,
                    $row->metric_name_internal
                );
                
                return [
                    'metric_value_id' => $row->metric_value_id,
                    'company_id' => $row->company_id,
                    'company_name' => $row->company_name,
                    'metric_name_internal' => $row->metric_name_internal,
                    'metric_name_display' => $row->metric_name_display,
                    'original_value' => $row->original_value,
                    'original_unit' => $row->original_unit,
                    'normalized_value' => $conversion['value'],
                    'normalized_unit' => $conversion['unit'],
                    'period_start_date' => $row->period_start_date ? $row->period_start_date->format('Y-m-d') : null,
                    'period_end_date' => $row->period_end_date ? $row->period_end_date->format('Y-m-d') : null,
                    'basin_name' => $row->basin_name,
                    'segment_name' => $row->segment_name,
                    'asset_name' => $row->asset_name,
                    'gross_or_net' => $row->gross_or_net,
                    'extraction_confidence_score' => $row->extraction_confidence_score,
                    'extraction_method' => $row->extraction_method,
                    'source_document_id' => $row->source_document_id,
                    'source_url' => $row->source_url,
                    'source_location' => $row->source_location,
                    'created_at' => $row->created_at ? $row->created_at->toIso8601String() : null,
                ];
            });

            return response()->json([
                'meta' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total
                ],
                'data' => $data
            ], 200);
            
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Metrics API Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Convert period string (e.g., "2024-Q4") to end date
     */
    private function convertPeriodToDate(string $period): string
    {
        preg_match('/^(\d{4})-Q([1-4])$/', $period, $matches);
        
        if (!$matches) {
            return '';
        }
        
        $year = $matches[1];
        $quarter = $matches[2];
        
        $endDates = [
            '1' => "$year-03-31",
            '2' => "$year-06-30",
            '3' => "$year-09-30",
            '4' => "$year-12-31",
        ];
        
        return $endDates[$quarter];
    }
    
    /**
     * Parse sort_by parameter
     */
    private function parseSortBy(string $sortBy): array
    {
        $parts = explode(':', $sortBy);
        
        if (count($parts) !== 2) {
            return ['period_end_date', 'desc'];
        }
        
        $field = $parts[0];
        $direction = strtolower($parts[1]);
        
        // Whitelist allowed sort fields
        $allowedFields = [
            'period_end_date',
            'period_start_date',
            'extraction_confidence_score',
            'created_at',
            'metric_name_internal',
            'basin_name',
            'segment_name',
        ];
        
        if (!in_array($field, $allowedFields)) {
            $field = 'period_end_date';
        }
        
        if (!in_array($direction, ['asc', 'desc'])) {
            $direction = 'desc';
        }
        
        // Handle qualified field names for sorting
        if (in_array($field, ['metric_name_internal'])) {
            $field = 'metric_definition.' . $field;
        } elseif (!in_array($field, ['created_at'])) {
            $field = 'metric_value.' . $field;
        }
        
        return [$field, $direction];
    }
}

