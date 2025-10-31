<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceDocument extends Model
{
    use HasFactory;

    protected $table = 'source_document';

    protected $primaryKey = 'source_document_id';

    public $timestamps = true;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'company_id',
        'source_type',
        'filing_type',
        'filing_date',
        'period_end_date',
        'source_url',
        'raw_text_blob_path',
        'file_format',
        'extraction_confidence_score',
    ];

    protected $casts = [
        'filing_date' => 'date',
        'period_end_date' => 'date',
        'extraction_confidence_score' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship to Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Relationship to MetricValues
     */
    public function metricValues()
    {
        return $this->hasMany(MetricValue::class, 'source_document_id', 'source_document_id');
    }
}
