<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Company;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\SourceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MetricsApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_company_id()
    {
        $response = $this->getJson('/api/metrics');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid parameters'
            ]);
    }

    /** @test */
    public function it_returns_metrics_for_a_company()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
            'metric_name_display' => 'Oil Production',
        ]);
        $sourceDoc = SourceDocument::factory()->create(['company_id' => $company->company_id]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'source_document_id' => $sourceDoc->source_document_id,
            'extracted_metric_value' => 12100.5,
            'extracted_metric_unit' => 'mbbl',
        ]);

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'meta' => ['limit', 'offset', 'total'],
                'data' => [
                    '*' => [
                        'metric_value_id',
                        'company_id',
                        'company_name',
                        'metric_name_internal',
                        'metric_name_display',
                        'original_value',
                        'original_unit',
                        'normalized_value',
                        'normalized_unit',
                        'period_start_date',
                        'period_end_date',
                        'basin_name',
                        'segment_name',
                        'asset_name',
                        'gross_or_net',
                        'extraction_confidence_score',
                        'extraction_method',
                        'source_document_id',
                        'source_url',
                        'source_location',
                        'created_at'
                    ]
                ]
            ]);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    /** @test */
    public function it_filters_by_period_end_date()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-09-30',
        ]);

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}&period_end_date=2024-12-31");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('2024-12-31', $response->json('data.0.period_end_date'));
    }

    /** @test */
    public function it_converts_period_parameter_to_date()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
        ]);

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}&period=2024-Q4");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
    }

    /** @test */
    public function it_filters_by_metric_names()
    {
        $company = Company::factory()->create();
        $metricDef1 = MetricDefinition::factory()->create(['metric_name_internal' => 'oil_production']);
        $metricDef2 = MetricDefinition::factory()->create(['metric_name_internal' => 'gas_production']);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef1->metric_id,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef2->metric_id,
        ]);

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}&metric=oil_production");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('oil_production', $response->json('data.0.metric_name_internal'));
    }

    /** @test */
    public function it_filters_by_multiple_metrics()
    {
        $company = Company::factory()->create();
        $metricDef1 = MetricDefinition::factory()->create(['metric_name_internal' => 'oil_production']);
        $metricDef2 = MetricDefinition::factory()->create(['metric_name_internal' => 'gas_production']);
        $metricDef3 = MetricDefinition::factory()->create(['metric_name_internal' => 'ngl_production']);

        MetricValue::factory()->create(['company_id' => $company->company_id, 'metric_id' => $metricDef1->metric_id]);
        MetricValue::factory()->create(['company_id' => $company->company_id, 'metric_id' => $metricDef2->metric_id]);
        MetricValue::factory()->create(['company_id' => $company->company_id, 'metric_id' => $metricDef3->metric_id]);

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}&metric=oil_production,gas_production");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));
    }

    /** @test */
    public function it_filters_by_basin()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'basin_name' => 'Permian Basin',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'basin_name' => 'Midland Basin',
        ]);

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}&basin=Permian Basin");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('Permian Basin', $response->json('data.0.basin_name'));
    }

    /** @test */
    public function it_filters_by_confidence_min()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.95,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.75,
        ]);

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}&confidence_min=0.9");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertGreaterThanOrEqual(0.9, $response->json('data.0.extraction_confidence_score'));
    }

    /** @test */
    public function it_respects_limit_and_offset()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->count(10)->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
        ]);

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}&limit=5&offset=0");

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.limit'));
        $this->assertEquals(10, $response->json('meta.total'));
        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function it_sorts_by_period_end_date_desc_by_default()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-03-31',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
        ]);

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('2024-12-31', $data[0]['period_end_date']);
        $this->assertEquals('2024-03-31', $data[1]['period_end_date']);
    }

    /** @test */
    public function it_sorts_by_custom_field()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.85,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.95,
        ]);

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}&sort_by=extraction_confidence_score:asc");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(0.85, $data[0]['extraction_confidence_score']);
        $this->assertEquals(0.95, $data[1]['extraction_confidence_score']);
    }

    /** @test */
    public function it_returns_400_for_invalid_date_format()
    {
        $company = Company::factory()->create();

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}&period_end_date=invalid-date");

        $response->assertStatus(400);
    }

    /** @test */
    public function it_validates_confidence_min_range()
    {
        $company = Company::factory()->create();

        $response = $this->getJson("/api/metrics?company_id={$company->company_id}&confidence_min=1.5");

        $response->assertStatus(400);
    }
}

