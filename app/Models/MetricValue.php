<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetricValue extends Model
{
    use HasFactory;

    protected $table = 'metric_value';

    protected $primaryKey = 'metric_value_id';

    public $timestamps = false;  // Only created_at exists, no updated_at

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'metric_id',
        'source_document_id',
        'extracted_metric_value',
        'extracted_metric_unit',
        'period_start_date',
        'period_end_date',
        'basin_name',
        'segment_name',
        'extraction_method',
        'extraction_confidence_score',
    ];

    protected $casts = [
        'extracted_metric_value' => 'decimal:6',
        'extraction_confidence_score' => 'decimal:2',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'created_at' => 'datetime',
    ];

    /**
     * Relationship to Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Relationship to MetricDefinition
     */
    public function metricDefinition()
    {
        return $this->belongsTo(MetricDefinition::class, 'metric_id', 'metric_id');
    }

    /**
     * Relationship to SourceDocument
     */
    public function sourceDocument()
    {
        return $this->belongsTo(SourceDocument::class, 'source_document_id', 'source_document_id');
    }

    /**
     * Scope to filter by company
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('metric_value.company_id', $companyId);
    }

    /**
     * Scope to filter by period end date
     */
    public function scopeByPeriodEndDate($query, $date)
    {
        if (empty($date)) {
            return $query;
        }

        return $query->where('metric_value.period_end_date', $date);
    }

    /**
     * Scope to filter by basin
     */
    public function scopeByBasin($query, $basin)
    {
        if (empty($basin)) {
            return $query;
        }

        return $query->where('metric_value.basin_name', $basin);
    }

    /**
     * Scope to filter by segment
     */
    public function scopeBySegment($query, $segment)
    {
        if (empty($segment)) {
            return $query;
        }

        return $query->where('metric_value.segment_name', $segment);
    }

    /**
     * Scope to filter by asset
     */
    public function scopeByAsset($query, $asset)
    {
        if (empty($asset)) {
            return $query;
        }

        return $query->where('metric_value.asset_name', $asset);
    }

    /**
     * Scope to filter by gross or net
     */
    public function scopeByGrossOrNet($query, $grossOrNet)
    {
        if (empty($grossOrNet)) {
            return $query;
        }

        return $query->where('metric_value.gross_or_net', $grossOrNet);
    }

    /**
     * Scope to filter by minimum confidence score
     */
    public function scopeByMinConfidence($query, $minConfidence)
    {
        if (is_null($minConfidence)) {
            return $query;
        }

        return $query->where('metric_value.extraction_confidence_score', '>=', $minConfidence);
    }
}
