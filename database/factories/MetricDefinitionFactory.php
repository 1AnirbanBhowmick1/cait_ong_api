<?php

namespace Database\Factories;

use App\Models\MetricDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class MetricDefinitionFactory extends Factory
{
    protected $model = MetricDefinition::class;

    public function definition(): array
    {
        $categories = ['OPERATIONAL', 'ACTIVITY', 'FINANCIAL'];
        $groups = ['Production', 'Wells', 'Drilling', 'Economics'];
        
        $metricNames = [
            ['Oil Production', 'oil_production', 'mbbl'],
            ['Gas Production', 'gas_production', 'mmcf'],
            ['NGL Production', 'ngl_production', 'mbbl'],
            ['Gross Wells Drilled', 'gross_wells_drilled', '#'],
            ['Total Lateral Length Drilled', 'total_lateral_length_drilled', 'ft'],
        ];
        
        $metric = fake()->randomElement($metricNames);
        
        return [
            'metric_category' => fake()->randomElement($categories),
            'metric_name_display' => $metric[0],
            'metric_name_internal' => $metric[1],
            'metric_unit' => $metric[2],
            'metric_group' => fake()->randomElement($groups),
            'is_active' => true,
            'created_at' => now(),
        ];
    }
}

