<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Company;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SummaryApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_period_or_period_end_date()
    {
        $response = $this->getJson('/api/summary');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid parameters'
            ]);
    }

    /** @test */
    public function it_returns_aggregated_summary_by_company()
    {
        $company1 = Company::factory()->create(['company_name' => 'Company A']);
        $company2 = Company::factory()->create(['company_name' => 'Company B']);
        
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
            'metric_name_display' => 'Oil Production',
            'metric_unit' => 'mbbl',
        ]);

        // Create multiple records for same company/metric
        MetricValue::factory()->create([
            'company_id' => $company1->company_id,
            'metric_id' => $metricDef->metric_id,
            'extracted_metric_value' => 100,
            'extracted_metric_unit' => 'mbbl',
            'period_end_date' => '2024-12-31',
            'extraction_confidence_score' => 0.9,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company1->company_id,
            'metric_id' => $metricDef->metric_id,
            'extracted_metric_value' => 150,
            'extracted_metric_unit' => 'mbbl',
            'period_end_date' => '2024-12-31',
            'extraction_confidence_score' => 0.95,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company2->company_id,
            'metric_id' => $metricDef->metric_id,
            'extracted_metric_value' => 200,
            'extracted_metric_unit' => 'mbbl',
            'period_end_date' => '2024-12-31',
            'extraction_confidence_score' => 0.85,
        ]);

        $response = $this->getJson('/api/summary?period_end_date=2024-12-31&metrics=oil_production');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'company_id',
                    'company_name',
                    'metric_name_internal',
                    'metric_name_display',
                    'aggregated_normalized_value',
                    'aggregated_normalized_unit',
                    'avg_confidence',
                    'record_count',
                ]
            ]);

        $data = $response->json();
        
        // Check Company A aggregation
        $companyA = collect($data)->firstWhere('company_id', $company1->company_id);
        $this->assertEquals(250, $companyA['aggregated_normalized_value']); // 100 + 150
        $this->assertEquals(0.93, $companyA['avg_confidence']); // (0.9 + 0.95) / 2
        $this->assertEquals(2, $companyA['record_count']);
        
        // Check Company B aggregation
        $companyB = collect($data)->firstWhere('company_id', $company2->company_id);
        $this->assertEquals(200, $companyB['aggregated_normalized_value']);
        $this->assertEquals(0.85, $companyB['avg_confidence']);
        $this->assertEquals(1, $companyB['record_count']);
    }

    /** @test */
    public function it_accepts_period_parameter_in_quarter_format()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 100,
        ]);

        $response = $this->getJson('/api/summary?period=2024-Q4&metrics=oil_production');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
    }

    /** @test */
    public function it_filters_by_company_ids()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $company3 = Company::factory()->create();
        
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company1->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 100,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company2->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 150,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company3->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 200,
        ]);

        $response = $this->getJson("/api/summary?period_end_date=2024-12-31&company_ids={$company1->company_id},{$company2->company_id}&metrics=oil_production");

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertCount(2, $data);
        $companyIds = collect($data)->pluck('company_id')->toArray();
        $this->assertContains($company1->company_id, $companyIds);
        $this->assertContains($company2->company_id, $companyIds);
        $this->assertNotContains($company3->company_id, $companyIds);
    }

    /** @test */
    public function it_filters_by_confidence_min()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 100,
            'extraction_confidence_score' => 0.95,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 50,
            'extraction_confidence_score' => 0.70,
        ]);

        $response = $this->getJson('/api/summary?period_end_date=2024-12-31&confidence_min=0.9&metrics=oil_production');

        $response->assertStatus(200);
        $data = $response->json();
        
        // Only the high-confidence record should be included
        $this->assertEquals(100, $data[0]['aggregated_normalized_value']);
        $this->assertEquals(1, $data[0]['record_count']);
    }

    /** @test */
    public function it_groups_by_basin()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'basin_name' => 'Permian Basin',
            'extracted_metric_value' => 100,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'basin_name' => 'Permian Basin',
            'extracted_metric_value' => 150,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'basin_name' => 'Midland Basin',
            'extracted_metric_value' => 200,
        ]);

        $response = $this->getJson('/api/summary?period_end_date=2024-12-31&group_by=basin&metrics=oil_production');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertCount(2, $data);
        
        $permian = collect($data)->firstWhere('basin_name', 'Permian Basin');
        $this->assertEquals(250, $permian['aggregated_normalized_value']);
        $this->assertNull($permian['company_id']);
        
        $midland = collect($data)->firstWhere('basin_name', 'Midland Basin');
        $this->assertEquals(200, $midland['aggregated_normalized_value']);
    }

    /** @test */
    public function it_groups_by_segment()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'segment_name' => 'Upstream',
            'extracted_metric_value' => 100,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'segment_name' => 'Upstream',
            'extracted_metric_value' => 150,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'segment_name' => 'Midstream',
            'extracted_metric_value' => 200,
        ]);

        $response = $this->getJson('/api/summary?period_end_date=2024-12-31&group_by=segment&metrics=oil_production');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertCount(2, $data);
        
        $upstream = collect($data)->firstWhere('segment_name', 'Upstream');
        $this->assertEquals(250, $upstream['aggregated_normalized_value']);
        $this->assertNull($upstream['company_id']);
        
        $midstream = collect($data)->firstWhere('segment_name', 'Midstream');
        $this->assertEquals(200, $midstream['aggregated_normalized_value']);
    }

    /** @test */
    public function it_handles_multiple_metrics()
    {
        $company = Company::factory()->create();
        $metricDef1 = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
        ]);
        $metricDef2 = MetricDefinition::factory()->create([
            'metric_name_internal' => 'gas_production',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef1->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 100,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef2->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 200,
        ]);

        $response = $this->getJson('/api/summary?period_end_date=2024-12-31&metrics=oil_production,gas_production');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertCount(2, $data);
        
        $metrics = collect($data)->pluck('metric_name_internal')->toArray();
        $this->assertContains('oil_production', $metrics);
        $this->assertContains('gas_production', $metrics);
    }

    /** @test */
    public function it_performs_unit_conversion_before_aggregation()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
            'metric_unit' => 'mbbl',
        ]);

        // One in barrels, one in mbarrels - should aggregate after conversion
        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 1000, // bbl
            'extracted_metric_unit' => 'bbl',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 5, // mbbl
            'extracted_metric_unit' => 'mbbl',
        ]);

        $response = $this->getJson('/api/summary?period_end_date=2024-12-31&metrics=oil_production');

        $response->assertStatus(200);
        $data = $response->json();
        
        // 1000 bbl = 1 mbbl, plus 5 mbbl = 6 mbbl total
        $this->assertEquals(6, $data[0]['aggregated_normalized_value']);
        $this->assertEquals('mbbl', $data[0]['aggregated_normalized_unit']);
    }

    /** @test */
    public function it_returns_400_for_invalid_period_format()
    {
        $response = $this->getJson('/api/summary?period=invalid');

        $response->assertStatus(400);
    }

    /** @test */
    public function it_sorts_results_by_group_and_metric()
    {
        $company1 = Company::factory()->create(['company_name' => 'Zebra Company']);
        $company2 = Company::factory()->create(['company_name' => 'Alpha Company']);
        
        $metricDef1 = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
        ]);
        $metricDef2 = MetricDefinition::factory()->create([
            'metric_name_internal' => 'gas_production',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company1->company_id,
            'metric_id' => $metricDef1->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 100,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company2->company_id,
            'metric_id' => $metricDef2->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 200,
        ]);

        $response = $this->getJson('/api/summary?period_end_date=2024-12-31&metrics=oil_production,gas_production');

        $response->assertStatus(200);
        $data = $response->json();
        
        // Should be sorted by company name first
        $this->assertEquals('Alpha Company', $data[0]['company_name']);
        $this->assertEquals('Zebra Company', $data[1]['company_name']);
    }
}

