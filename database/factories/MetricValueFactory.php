<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\SourceDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class MetricValueFactory extends Factory
{
    protected $model = MetricValue::class;

    public function definition(): array
    {
        $basins = ['Permian Basin', 'Midland Basin', 'Delaware Basin', null];
        $segments = ['Upstream', 'Midstream', 'Downstream', null];
        $grossOrNet = ['gross', 'net', null];
        $methods = ['html_table_reader', 'xbrl_parser', 'pdf_extractor', 'EGDAR', 'LLM'];

        return [
            'company_id' => Company::factory(),
            'metric_id' => MetricDefinition::factory(),
            'source_document_id' => SourceDocument::factory(),
            'extracted_metric_value' => fake()->randomFloat(6, 100, 100000),
            'extracted_metric_unit' => 'mbbl',
            'period_start_date' => fake()->dateTimeBetween('-1 year', '-3 months'),
            'period_end_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'basin_name' => fake()->randomElement($basins),
            'segment_name' => fake()->randomElement($segments),
            'asset_name' => fake()->optional()->word(),
            'gross_or_net' => fake()->randomElement($grossOrNet),
            'extraction_method' => fake()->randomElement($methods),
            'extraction_confidence_score' => fake()->randomFloat(2, 0.75, 0.99),
            'source_location' => fake()->optional()->word(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
