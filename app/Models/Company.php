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
        'company_type',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to filter only active companies
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
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
