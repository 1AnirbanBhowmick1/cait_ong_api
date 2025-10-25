<?php

namespace App\Http\Controllers;

use App\Models\MetricValue;
use App\Services\UnitConverterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricDetailController extends Controller
{
    protected $unitConverter;
    
    public function __construct(UnitConverterService $unitConverter)
    {
        $this->unitConverter = $unitConverter;
    }
    
    /**
     * Display full metadata for a single metric row
     *
     * @param  int  $metricValueId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($metricValueId)
    {
        try {
            // Fetch the metric value with all related data
            $metricValue = MetricValue::query()
                ->select([
                    'metric_value.*',
                    'metric_definition.metric_name_internal',
                    'metric_definition.metric_name_display',
                    'metric_definition.metric_unit as metric_definition_unit',
                    'companies.company_name',
                    'source_document.source_url',
                    DB::raw('source_document.filing_date::date as filing_date'),
                    'source_document.source_type'
                ])
                ->join('metric_definition', 'metric_value.metric_id', '=', 'metric_definition.metric_id')
                ->join('companies', 'metric_value.company_id', '=', 'companies.company_id')
                ->leftJoin('source_document', 'metric_value.source_document_id', '=', 'source_document.source_document_id')
                ->where('metric_value.metric_value_id', $metricValueId)
                ->first();

            if (!$metricValue) {
                return response()->json([
                    'error' => 'Metric value not found'
                ], 404);
            }

            // Perform unit conversion
            $conversion = $this->unitConverter->convert(
                $metricValue->extracted_metric_value,
                $metricValue->extracted_metric_unit,
                $metricValue->metric_name_internal
            );

            // Run derived checks
            $derivedChecks = $this->runDerivedChecks($metricValue);

            // Build response
            $response = [
                'metric_value_id' => $metricValue->metric_value_id,
                'company_id' => $metricValue->company_id,
                'company_name' => $metricValue->company_name,
                'metric_id' => $metricValue->metric_id,
                'metric_name_internal' => $metricValue->metric_name_internal,
                'metric_name_display' => $metricValue->metric_name_display,
                'metric_definition_unit' => $metricValue->metric_definition_unit,
                'original_value' => $metricValue->extracted_metric_value,
                'original_unit' => $metricValue->extracted_metric_unit,
                'normalized_value' => $conversion['value'],
                'normalized_unit' => $conversion['unit'],
                'period_start_date' => $metricValue->period_start_date ? $metricValue->period_start_date->format('Y-m-d') : null,
                'period_end_date' => $metricValue->period_end_date ? $metricValue->period_end_date->format('Y-m-d') : null,
                'basin_name' => $metricValue->basin_name,
                'segment_name' => $metricValue->segment_name,
                'asset_name' => null, // Column doesn't exist in your schema
                'gross_or_net' => null, // Column doesn't exist in your schema
                'extraction_confidence_score' => $metricValue->extraction_confidence_score,
                'extraction_method' => $metricValue->extraction_method,
                'source_document_id' => $metricValue->source_document_id,
                'source_url' => $metricValue->source_url,
                'source_location' => null, // Column doesn't exist in your schema
                'filing_date' => $metricValue->filing_date ?? null,
                'source_type' => $metricValue->source_type,
                'created_at' => $metricValue->created_at ? $metricValue->created_at->toIso8601String() : null,
                'derived_checks' => $derivedChecks,
            ];

            return response()->json($response, 200);
            
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Metric Detail API Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Run derived checks for data quality and consistency
     */
    private function runDerivedChecks($metricValue): array
    {
        $checks = [];
        
        // Check 1: Confidence score threshold
        if ($metricValue->extraction_confidence_score < 0.8) {
            $checks[] = [
                'name' => 'Low confidence score',
                'status' => 'flag',
                'reason' => sprintf(
                    'Confidence score %.2f is below recommended threshold of 0.80',
                    $metricValue->extraction_confidence_score
                )
            ];
        } else {
            $checks[] = [
                'name' => 'Confidence score check',
                'status' => 'ok',
                'reason' => sprintf('Confidence score %.2f meets quality threshold', $metricValue->extraction_confidence_score)
            ];
        }
        
        // Check 2: BOE consistency (if this is a production metric)
        if (in_array($metricValue->metric_name_internal, ['oil_production', 'gas_production', 'ngl_production', 'boe_production'])) {
            $boeCheck = $this->checkBOEConsistency($metricValue);
            if ($boeCheck) {
                $checks[] = $boeCheck;
            }
        }
        
        // Check 3: Period duration check
        if ($metricValue->period_start_date && $metricValue->period_end_date) {
            $daysDiff = $metricValue->period_start_date->diffInDays($metricValue->period_end_date);
            
            if ($daysDiff < 28 || $daysDiff > 366) {
                $checks[] = [
                    'name' => 'Period duration check',
                    'status' => 'flag',
                    'reason' => sprintf('Period duration of %d days is unusual (expected 28-366)', $daysDiff)
                ];
            } else {
                $checks[] = [
                    'name' => 'Period duration check',
                    'status' => 'ok',
                    'reason' => sprintf('Period duration of %d days is within normal range', $daysDiff)
                ];
            }
        }
        
        // Check 4: Value reasonability (basic sanity check)
        if ($metricValue->extracted_metric_value !== null) {
            if ($metricValue->extracted_metric_value < 0) {
                $checks[] = [
                    'name' => 'Value sanity check',
                    'status' => 'flag',
                    'reason' => 'Negative value detected for production metric'
                ];
            } elseif ($metricValue->extracted_metric_value == 0) {
                $checks[] = [
                    'name' => 'Value sanity check',
                    'status' => 'flag',
                    'reason' => 'Zero value may indicate missing or null data'
                ];
            } else {
                $checks[] = [
                    'name' => 'Value sanity check',
                    'status' => 'ok',
                    'reason' => 'Value is within expected range'
                ];
            }
        }
        
        return $checks;
    }
    
    /**
     * Check BOE consistency between oil, gas, NGL and total BOE
     */
    private function checkBOEConsistency($metricValue): ?array
    {
        // Fetch related production metrics for the same company and period
        $relatedMetrics = MetricValue::query()
            ->select([
                'metric_definition.metric_name_internal',
                'metric_value.extracted_metric_value',
                'metric_value.extracted_metric_unit'
            ])
            ->join('metric_definition', 'metric_value.metric_id', '=', 'metric_definition.metric_id')
            ->where('metric_value.company_id', $metricValue->company_id)
            ->where('metric_value.period_end_date', $metricValue->period_end_date)
            ->whereIn('metric_definition.metric_name_internal', ['oil_production', 'gas_production', 'ngl_production', 'boe_production'])
            ->get();
        
        if ($relatedMetrics->count() < 2) {
            return null; // Not enough data to cross-check
        }
        
        // Convert all to BOE equivalents
        $values = [];
        foreach ($relatedMetrics as $metric) {
            $conversion = $this->unitConverter->convert(
                $metric->extracted_metric_value,
                $metric->extracted_metric_unit,
                $metric->metric_name_internal
            );
            $values[$metric->metric_name_internal] = $conversion['value'] ?? 0;
        }
        
        // Calculate expected BOE (Oil + NGL + Gas/6)
        $expectedBOE = 0;
        if (isset($values['oil_production'])) {
            $expectedBOE += $values['oil_production'];
        }
        if (isset($values['ngl_production'])) {
            $expectedBOE += $values['ngl_production'];
        }
        if (isset($values['gas_production'])) {
            // Convert gas to BOE (6 MCF = 1 BOE typically)
            $expectedBOE += $values['gas_production'] / 6;
        }
        
        // Compare with reported BOE
        if (isset($values['boe_production']) && $expectedBOE > 0) {
            $reportedBOE = $values['boe_production'];
            $percentDiff = abs(($reportedBOE - $expectedBOE) / $expectedBOE) * 100;
            
            if ($percentDiff > 10) {
                return [
                    'name' => 'BOE consistency check',
                    'status' => 'flag',
                    'reason' => sprintf(
                        'Calculated BOE (%.2f) differs from reported BOE (%.2f) by %.1f%%',
                        $expectedBOE,
                        $reportedBOE,
                        $percentDiff
                    )
                ];
            } else {
                return [
                    'name' => 'BOE consistency check',
                    'status' => 'ok',
                    'reason' => sprintf('BOE calculation matches within %.1f%% tolerance', $percentDiff)
                ];
            }
        }
        
        return null;
    }
}

