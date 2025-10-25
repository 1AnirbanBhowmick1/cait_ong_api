<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Company;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\SourceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MetricDetailApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_full_metadata_for_a_single_metric()
    {
        $company = Company::factory()->create(['company_name' => 'Test Company']);
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
            'metric_name_display' => 'Oil Production',
            'metric_unit' => 'mbbl',
        ]);
        $sourceDoc = SourceDocument::factory()->create([
            'company_id' => $company->company_id,
            'source_url' => 'https://www.sec.gov/test',
            'source_type' => 'SEC_FILING',
        ]);

        $metricValue = MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'source_document_id' => $sourceDoc->source_document_id,
            'extracted_metric_value' => 1000,
            'extracted_metric_unit' => 'bbl',
            'period_start_date' => '2024-10-01',
            'period_end_date' => '2024-12-31',
            'basin_name' => 'Permian Basin',
            'segment_name' => 'Upstream',
            'extraction_confidence_score' => 0.95,
            'extraction_method' => 'html_table_reader',
        ]);

        $response = $this->getJson("/api/metric/{$metricValue->metric_value_id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'metric_value_id',
                'company_id',
                'company_name',
                'metric_id',
                'metric_name_internal',
                'metric_name_display',
                'metric_definition_unit',
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
                'filing_date',
                'source_type',
                'created_at',
                'derived_checks' => [
                    '*' => ['name', 'status', 'reason']
                ]
            ]);

        $data = $response->json();
        
        $this->assertEquals($metricValue->metric_value_id, $data['metric_value_id']);
        $this->assertEquals('Test Company', $data['company_name']);
        $this->assertEquals('oil_production', $data['metric_name_internal']);
        $this->assertEquals(1000, $data['original_value']);
        $this->assertEquals('bbl', $data['original_unit']);
        $this->assertEquals(1, $data['normalized_value']); // 1000 bbl = 1 mbbl
        $this->assertEquals('mbbl', $data['normalized_unit']);
        $this->assertEquals('Permian Basin', $data['basin_name']);
        $this->assertEquals('Upstream', $data['segment_name']);
        $this->assertIsArray($data['derived_checks']);
        $this->assertNotEmpty($data['derived_checks']);
    }

    /** @test */
    public function it_returns_404_for_non_existent_metric()
    {
        $response = $this->getJson('/api/metric/99999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Metric value not found'
            ]);
    }

    /** @test */
    public function it_includes_source_document_information()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();
        $sourceDoc = SourceDocument::factory()->create([
            'company_id' => $company->company_id,
            'source_url' => 'https://www.sec.gov/filing/123',
            'source_type' => 'SEC_FILING',
            'filing_date' => '2024-11-15',
        ]);

        $metricValue = MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'source_document_id' => $sourceDoc->source_document_id,
        ]);

        $response = $this->getJson("/api/metric/{$metricValue->metric_value_id}");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertEquals('https://www.sec.gov/filing/123', $data['source_url']);
        $this->assertEquals('SEC_FILING', $data['source_type']);
        $this->assertEquals('2024-11-15', $data['filing_date']);
    }

    /** @test */
    public function it_handles_metric_without_source_document()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        $metricValue = MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'source_document_id' => null,
        ]);

        $response = $this->getJson("/api/metric/{$metricValue->metric_value_id}");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertNull($data['source_document_id']);
        $this->assertNull($data['source_url']);
        $this->assertNull($data['source_type']);
    }

    /** @test */
    public function it_performs_unit_conversion()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'gas_production',
            'metric_unit' => 'mmcf',
        ]);

        // Store in MCF, should convert to MMCF
        $metricValue = MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extracted_metric_value' => 5000,
            'extracted_metric_unit' => 'mcf',
        ]);

        $response = $this->getJson("/api/metric/{$metricValue->metric_value_id}");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertEquals(5000, $data['original_value']);
        $this->assertEquals('mcf', $data['original_unit']);
        $this->assertEquals(5, $data['normalized_value']); // 5000 MCF = 5 MMCF
        $this->assertEquals('mmcf', $data['normalized_unit']);
    }

    /** @test */
    public function it_includes_derived_checks()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        $metricValue = MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.95,
        ]);

        $response = $this->getJson("/api/metric/{$metricValue->metric_value_id}");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertIsArray($data['derived_checks']);
        $this->assertNotEmpty($data['derived_checks']);
        
        // Check that each derived check has required fields
        foreach ($data['derived_checks'] as $check) {
            $this->assertArrayHasKey('name', $check);
            $this->assertArrayHasKey('status', $check);
            $this->assertArrayHasKey('reason', $check);
            $this->assertContains($check['status'], ['ok', 'flag']);
        }
    }

    /** @test */
    public function it_flags_low_confidence_scores()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        $metricValue = MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.65, // Low confidence
        ]);

        $response = $this->getJson("/api/metric/{$metricValue->metric_value_id}");

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $confidenceCheck = collect($data['derived_checks'])
            ->firstWhere('name', 'Low confidence score');
        
        $this->assertNotNull($confidenceCheck);
        $this->assertEquals('flag', $confidenceCheck['status']);
    }

    /** @test */
    public function it_flags_unusual_period_durations()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        // Period of only 5 days - unusual
        $metricValue = MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_start_date' => '2024-12-01',
            'period_end_date' => '2024-12-06',
        ]);

        $response = $this->getJson("/api/metric/{$metricValue->metric_value_id}");

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $periodCheck = collect($data['derived_checks'])
            ->firstWhere('name', 'Period duration check');
        
        $this->assertNotNull($periodCheck);
        $this->assertEquals('flag', $periodCheck['status']);
    }

    /** @test */
    public function it_flags_negative_values()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
        ]);

        $metricValue = MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extracted_metric_value' => -100, // Negative production!
        ]);

        $response = $this->getJson("/api/metric/{$metricValue->metric_value_id}");

        $response->assertStatus(200);
        
        $data = $response->json();
        
        $valueCheck = collect($data['derived_checks'])
            ->firstWhere('name', 'Value sanity check');
        
        $this->assertNotNull($valueCheck);
        $this->assertEquals('flag', $valueCheck['status']);
        $this->assertStringContainsString('Negative', $valueCheck['reason']);
    }

    /** @test */
    public function it_checks_boe_consistency_when_related_metrics_exist()
    {
        $company = Company::factory()->create();
        
        $oilMetric = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
        ]);
        $gasMetric = MetricDefinition::factory()->create([
            'metric_name_internal' => 'gas_production',
        ]);
        $boeMetric = MetricDefinition::factory()->create([
            'metric_name_internal' => 'boe_production',
        ]);

        // Create related metrics for same period
        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $oilMetric->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 100,
            'extracted_metric_unit' => 'mbbl',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $gasMetric->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 600, // Should equal 100 BOE (600/6)
            'extracted_metric_unit' => 'mmcf',
        ]);

        $boeValue = MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $boeMetric->metric_id,
            'period_end_date' => '2024-12-31',
            'extracted_metric_value' => 200, // Oil (100) + Gas (100) = 200 BOE
            'extracted_metric_unit' => 'mboe',
        ]);

        $response = $this->getJson("/api/metric/{$boeValue->metric_value_id}");

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Should have BOE consistency check
        $boeCheck = collect($data['derived_checks'])
            ->firstWhere('name', 'BOE consistency check');
        
        $this->assertNotNull($boeCheck);
        $this->assertContains($boeCheck['status'], ['ok', 'flag']);
    }
}

