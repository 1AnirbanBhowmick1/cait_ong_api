<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetricDefinition extends Model
{
    use HasFactory;

    protected $table = 'metric_definition';

    protected $primaryKey = 'metric_id';

    public $timestamps = true;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'metric_category',
        'metric_name_display',
        'metric_name_internal',
        'metric_unit',
        'metric_group',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Relationship to MetricValues
     */
    public function metricValues()
    {
        return $this->hasMany(MetricValue::class, 'metric_id', 'metric_id');
    }
}
