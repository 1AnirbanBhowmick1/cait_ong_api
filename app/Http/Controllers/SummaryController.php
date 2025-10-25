<?php

namespace App\Http\Controllers;

use App\Models\MetricValue;
use App\Services\UnitConverterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SummaryController extends Controller
{
    protected $unitConverter;
    
    public function __construct(UnitConverterService $unitConverter)
    {
        $this->unitConverter = $unitConverter;
    }
    
    /**
     * Display aggregated KPIs per company for a given period
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'period_end_date' => 'required_without:period|date_format:Y-m-d',
                'period' => 'required_without:period_end_date|string|regex:/^\d{4}-Q[1-4]$/',
                'metrics' => 'nullable|string',
                'group_by' => 'nullable|in:company,basin,segment',
                'company_ids' => 'nullable|string',
                'confidence_min' => 'nullable|numeric|min:0|max:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Invalid parameters',
                    'messages' => $validator->errors()
                ], 400);
            }

            // Get validated parameters
            $periodEndDate = $request->input('period_end_date');
            $period = $request->input('period');
            $metricsParam = $request->input('metrics', 'boe_production,oil_production,gas_production');
            $groupBy = $request->input('group_by', 'company');
            $companyIdsParam = $request->input('company_ids');
            $confidenceMin = $request->input('confidence_min');

            // Handle period parameter (convert Q4-2024 to date)
            if ($period && !$periodEndDate) {
                $periodEndDate = $this->convertPeriodToDate($period);
                
                if (!$periodEndDate) {
                    return response()->json([
                        'error' => 'Invalid period format'
                    ], 400);
                }
            }

            // Parse metrics (comma-separated)
            $metricsArray = array_map('trim', explode(',', $metricsParam));
            
            // Parse company IDs (comma-separated)
            $companyIdsArray = null;
            if ($companyIdsParam) {
                $companyIdsArray = array_map('intval', array_filter(explode(',', $companyIdsParam)));
            }

            // Fetch all metric values with filters (we'll aggregate in app layer for unit conversion)
            $query = MetricValue::query()
                ->select([
                    'metric_value.metric_value_id',
                    'metric_value.company_id',
                    'companies.company_name',
                    'metric_value.metric_id',
                    'metric_definition.metric_name_internal',
                    'metric_definition.metric_name_display',
                    'metric_definition.metric_unit',
                    'metric_value.extracted_metric_value',
                    'metric_value.extracted_metric_unit',
                    'metric_value.basin_name',
                    'metric_value.segment_name',
                    'metric_value.extraction_confidence_score'
                ])
                ->join('metric_definition', 'metric_value.metric_id', '=', 'metric_definition.metric_id')
                ->join('companies', 'metric_value.company_id', '=', 'companies.company_id')
                ->where('metric_value.period_end_date', $periodEndDate);

            // Apply metric filter
            if (!empty($metricsArray)) {
                $query->whereIn('metric_definition.metric_name_internal', $metricsArray);
            }

            // Apply company IDs filter
            if ($companyIdsArray) {
                $query->whereIn('metric_value.company_id', $companyIdsArray);
            }

            // Apply confidence filter
            if ($confidenceMin !== null) {
                $query->where('metric_value.extraction_confidence_score', '>=', $confidenceMin);
            }

            $results = $query->get();

            // Group and aggregate in application layer (for proper unit conversion)
            $aggregated = $this->aggregateResults($results, $groupBy);

            return response()->json($aggregated, 200);
            
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Summary API Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Aggregate results by group
     */
    private function aggregateResults($results, string $groupBy): array
    {
        $grouped = [];
        
        foreach ($results as $row) {
            // Determine grouping key
            $groupKey = $this->getGroupKey($row, $groupBy);
            
            // Create unique key for this group + metric combination
            $key = $groupKey . '||' . $row->metric_name_internal;
            
            // Initialize group if not exists
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'group_key' => $groupKey,
                    'group_type' => $groupBy,
                    'company_id' => $groupBy === 'company' ? $row->company_id : null,
                    'company_name' => $groupBy === 'company' ? $row->company_name : null,
                    'basin_name' => $groupBy === 'basin' ? $row->basin_name : null,
                    'segment_name' => $groupBy === 'segment' ? $row->segment_name : null,
                    'metric_name_internal' => $row->metric_name_internal,
                    'metric_name_display' => $row->metric_name_display,
                    'metric_unit' => $row->metric_unit,
                    'values' => [],
                    'confidence_scores' => [],
                ];
            }
            
            // Convert value to normalized unit
            $conversion = $this->unitConverter->convert(
                $row->extracted_metric_value,
                $row->extracted_metric_unit,
                $row->metric_name_internal
            );
            
            // Add to aggregation arrays
            $grouped[$key]['values'][] = $conversion['value'] ?? 0;
            $grouped[$key]['confidence_scores'][] = $row->extraction_confidence_score;
            $grouped[$key]['normalized_unit'] = $conversion['unit'];
        }
        
        // Calculate aggregates
        $output = [];
        foreach ($grouped as $item) {
            $output[] = [
                'company_id' => $item['company_id'],
                'company_name' => $item['company_name'],
                'basin_name' => $item['basin_name'],
                'segment_name' => $item['segment_name'],
                'metric_name_internal' => $item['metric_name_internal'],
                'metric_name_display' => $item['metric_name_display'],
                'aggregated_normalized_value' => !empty($item['values']) ? array_sum($item['values']) : 0,
                'aggregated_normalized_unit' => $item['normalized_unit'] ?? $item['metric_unit'],
                'avg_confidence' => !empty($item['confidence_scores']) 
                    ? round(array_sum($item['confidence_scores']) / count($item['confidence_scores']), 2)
                    : 0,
                'record_count' => count($item['values']),
            ];
        }
        
        // Sort output
        usort($output, function($a, $b) use ($groupBy) {
            if ($groupBy === 'company') {
                $cmp = strcmp($a['company_name'] ?? '', $b['company_name'] ?? '');
            } elseif ($groupBy === 'basin') {
                $cmp = strcmp($a['basin_name'] ?? '', $b['basin_name'] ?? '');
            } else { // segment
                $cmp = strcmp($a['segment_name'] ?? '', $b['segment_name'] ?? '');
            }
            
            if ($cmp === 0) {
                return strcmp($a['metric_name_internal'], $b['metric_name_internal']);
            }
            return $cmp;
        });
        
        return $output;
    }
    
    /**
     * Get group key based on grouping type
     */
    private function getGroupKey($row, string $groupBy): string
    {
        switch ($groupBy) {
            case 'basin':
                return 'basin_' . ($row->basin_name ?? 'unknown');
            case 'segment':
                return 'segment_' . ($row->segment_name ?? 'unknown');
            case 'company':
            default:
                return 'company_' . $row->company_id;
        }
    }
    
    /**
     * Convert period string (e.g., "2024-Q4") to end date
     */
    private function convertPeriodToDate(string $period): ?string
    {
        preg_match('/^(\d{4})-Q([1-4])$/', $period, $matches);
        
        if (!$matches) {
            return null;
        }
        
        $year = $matches[1];
        $quarter = $matches[2];
        
        $endDates = [
            '1' => "$year-03-31",
            '2' => "$year-06-30",
            '3' => "$year-09-30",
            '4' => "$year-12-31",
        ];
        
        return $endDates[$quarter] ?? null;
    }
}

