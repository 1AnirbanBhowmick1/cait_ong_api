<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $primaryKey = 'company_id';

    public $timestamps = true;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'company_name',
        'ticker_symbol',
        'sec_cik_number',
        'sic_code',
        'sic_description',
        'entity_type',
        'extraction_flag',
        'admin_approval_flag',
    ];

    protected $casts = [
        'extraction_flag' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to filter companies that have been extracted (have additional details)
     * Checks if sic_code is populated instead of extraction_flag
     */
    /**
     * Scope to filter companies that have been extracted (have additional details)
     * Checks if sic_code is populated (not null)
     */
    public function scopeExtracted($query)
    {
        return $query->whereNotNull('sic_code');
    }

    /**
     * Scope to search companies by name or ticker
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('company_name', 'ILIKE', '%'.$search.'%')
                ->orWhere('ticker_symbol', 'ILIKE', '%'.$search.'%');
        });
    }

    /**
     * Check if company is oil & gas based on SIC code
     * Uses the same range validation as SecCompanyLookupService
     */
    public function isOilGasCompany(): bool
    {
        if (!$this->sic_code) {
            return false;
        }

        $sicCode = (int) $this->sic_code;

        // Oil & Gas SIC Code Ranges (must match SecCompanyLookupService)
        $oilGasSicRanges = [
            [1300, 1389],  // Crude Petroleum and Natural Gas
            [2911, 2911],  // Petroleum Refining
            [2990, 2999],  // Petroleum and Coal Products
            [4612, 4613],  // Petroleum Pipelines
            [4922, 4925],  // Natural Gas Transmission and Distribution
            [5172, 5172],  // Petroleum Bulk Stations and Terminals
        ];

        // Check if SIC code falls within any of the defined ranges
        foreach ($oilGasSicRanges as $range) {
            [$start, $end] = $range;
            if ($sicCode >= $start && $sicCode <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Relationship to MetricValues
     */
    public function metricValues()
    {
        return $this->hasMany(MetricValue::class, 'company_id', 'company_id');
    }

    /**
     * Relationship to SourceDocuments
     */
    public function sourceDocuments()
    {
        return $this->hasMany(SourceDocument::class, 'company_id', 'company_id');
    }
}
