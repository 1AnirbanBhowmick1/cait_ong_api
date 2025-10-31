<?php

namespace App\Http\Controllers;

use App\Models\MetricValue;
use App\Services\UnitConverterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConfidenceController extends Controller
{
    protected $unitConverter;

    public function __construct(UnitConverterService $unitConverter)
    {
        $this->unitConverter = $unitConverter;
    }

    /**
     * Return rows below a confidence threshold for review and triage
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'threshold' => 'nullable|numeric|min:0|max:1',
                'company_id' => 'nullable|integer|min:1',
                'period_end_date' => 'nullable|date_format:Y-m-d',
                'metric' => 'nullable|string',
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
                'sort_by' => 'nullable|string|regex:/^[a-z_]+:(asc|desc)$/',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Invalid parameters',
                    'messages' => $validator->errors(),
                ], 400);
            }

            // Get validated parameters with defaults
            $threshold = $request->input('threshold', 1);
            $companyId = $request->input('company_id');
            $periodEndDate = $request->input('period_end_date');
            $metrics = $request->input('metric');
            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);
            $sortBy = $request->input('sort_by', 'confidence:asc');

            // Parse metrics (comma-separated)
            $metricsArray = null;
            if ($metrics) {
                $metricsArray = array_map('trim', explode(',', $metrics));
            }

            // Build base query with joins
            $query = MetricValue::query()
                ->select([
                    'metric_value.metric_value_id',
                    'companies.company_name',
                    'metric_value.company_id',
                    'metric_definition.metric_name_display',
                    'metric_definition.metric_name_internal',
                    'metric_value.extracted_metric_value',
                    'metric_value.extracted_metric_unit',
                    'metric_value.extraction_confidence_score',
                    'metric_value.period_end_date',
                    'metric_value.basin_name',
                    'metric_value.segment_name',
                    'metric_value.extraction_method',
                    'metric_value.source_document_id',
                    'source_document.source_url',
                ])
                ->join('metric_definition', 'metric_value.metric_id', '=', 'metric_definition.metric_id')
                ->join('companies', 'metric_value.company_id', '=', 'companies.company_id')
                ->leftJoin('source_document', 'metric_value.source_document_id', '=', 'source_document.source_document_id')
                ->where('metric_value.extraction_confidence_score', '<', $threshold);

            // Apply optional filters
            if ($companyId) {
                $query->where('metric_value.company_id', $companyId);
            }

            if ($periodEndDate) {
                $query->where('metric_value.period_end_date', $periodEndDate);
            }

            if ($metricsArray) {
                $query->whereIn('metric_definition.metric_name_internal', $metricsArray);
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

            // Process results with unit conversion and review hints
            $data = $results->map(function ($row) {
                // Perform unit conversion
                $conversion = $this->unitConverter->convert(
                    $row->extracted_metric_value,
                    $row->extracted_metric_unit,
                    $row->metric_name_internal
                );

                // Generate review hint
                $reviewHint = $this->generateReviewHint($row);

                return [
                    'metric_value_id' => $row->metric_value_id,
                    'company_id' => $row->company_id,
                    'company_name' => $row->company_name,
                    'metric_name_display' => $row->metric_name_display,
                    'original_value' => $row->extracted_metric_value,
                    'original_unit' => $row->extracted_metric_unit,
                    'normalized_value' => $conversion['value'],
                    'normalized_unit' => $conversion['unit'],
                    'extraction_confidence_score' => $row->extraction_confidence_score,
                    'period_end_date' => $row->period_end_date ? $row->period_end_date->format('Y-m-d') : null,
                    'basin_name' => $row->basin_name,
                    'segment_name' => $row->segment_name,
                    'source_document_id' => $row->source_document_id,
                    'source_url' => $row->source_url,
                    'extraction_method' => $row->extraction_method,
                    'review_hint' => $reviewHint,
                ];
            });

            return response()->json([
                'meta' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total,
                    'threshold' => $threshold,
                ],
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Confidence API Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Generate review hint based on extraction method and confidence score
     */
    private function generateReviewHint($row): string
    {
        $score = $row->extraction_confidence_score;
        $method = $row->extraction_method;

        $hints = [];

        // Severity level based on confidence
        if ($score < 0.5) {
            $severityLevel = 'Critical';
        } elseif ($score < 0.7) {
            $severityLevel = 'High';
        } else {
            $severityLevel = 'Medium';
        }

        $hints[] = sprintf('%s priority (confidence: %.2f)', $severityLevel, $score);

        // Method-specific hints
        switch (strtolower($method ?? '')) {
            case 'ocr':
            case 'pdf_ocr':
            case 'image_ocr':
                $hints[] = 'extracted from image/PDF via OCR - may contain recognition errors';
                break;

            case 'html_table_reader':
            case 'html_parser':
                $hints[] = 'extracted from HTML table - verify structure parsing';
                break;

            case 'xbrl':
            case 'xbrl_parser':
            case 'edgar':
            case 'egdar':
                $hints[] = 'extracted from XBRL/EDGAR filing - check tag mapping';
                break;

            case 'llm':
            case 'gpt':
            case 'ai':
                $hints[] = 'extracted using AI/LLM - requires manual verification';
                break;

            case 'manual':
                $hints[] = 'manually entered - verify source accuracy';
                break;

            default:
                if ($method) {
                    $hints[] = sprintf('extracted via %s - review extraction quality', $method);
                } else {
                    $hints[] = 'extraction method unknown - requires investigation';
                }
        }

        // Additional checks
        if ($row->extracted_metric_value === null || $row->extracted_metric_value == 0) {
            $hints[] = 'zero or null value detected';
        }

        if (! $row->source_url) {
            $hints[] = 'no source URL available for verification';
        }

        return implode('; ', $hints);
    }

    /**
     * Parse sort_by parameter
     */
    private function parseSortBy(string $sortBy): array
    {
        // Map 'confidence' shorthand to actual column
        if (str_starts_with($sortBy, 'confidence:')) {
            $sortBy = str_replace('confidence:', 'extraction_confidence_score:', $sortBy);
        }

        $parts = explode(':', $sortBy);

        if (count($parts) !== 2) {
            return ['metric_value.extraction_confidence_score', 'asc'];
        }

        $field = $parts[0];
        $direction = strtolower($parts[1]);

        // Whitelist allowed sort fields
        $allowedFields = [
            'extraction_confidence_score',
            'period_end_date',
            'metric_name_display',
            'company_name',
            'extracted_metric_value',
        ];

        if (! in_array($field, $allowedFields)) {
            $field = 'extraction_confidence_score';
        }

        if (! in_array($direction, ['asc', 'desc'])) {
            $direction = 'asc';
        }

        // Handle qualified field names for sorting
        if (in_array($field, ['extraction_confidence_score', 'period_end_date', 'extracted_metric_value'])) {
            $field = 'metric_value.'.$field;
        } elseif ($field === 'metric_name_display') {
            $field = 'metric_definition.'.$field;
        } elseif ($field === 'company_name') {
            $field = 'companies.'.$field;
        }

        return [$field, $direction];
    }
}
