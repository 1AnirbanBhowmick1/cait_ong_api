<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Company;
use App\Models\MetricDefinition;
use App\Models\MetricValue;
use App\Models\SourceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConfidenceApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_metrics_below_default_threshold()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        // High confidence - should not appear
        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.95,
        ]);

        // Low confidence - should appear
        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.65,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.45,
        ]);

        $response = $this->getJson('/api/confidence');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'meta' => ['limit', 'offset', 'total', 'threshold'],
                'data' => [
                    '*' => [
                        'metric_value_id',
                        'company_id',
                        'company_name',
                        'metric_name_display',
                        'original_value',
                        'original_unit',
                        'normalized_value',
                        'normalized_unit',
                        'extraction_confidence_score',
                        'period_end_date',
                        'basin_name',
                        'segment_name',
                        'source_document_id',
                        'source_url',
                        'extraction_method',
                        'review_hint',
                    ]
                ]
            ]);

        $data = $response->json();
        
        // Default threshold is 0.8, so 2 records should match
        $this->assertEquals(2, $data['meta']['total']);
        $this->assertEquals(0.8, $data['meta']['threshold']);
    }

    /** @test */
    public function it_accepts_custom_threshold()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.65,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.55,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.45,
        ]);

        $response = $this->getJson('/api/confidence?threshold=0.6');

        $response->assertStatus(200);
        $data = $response->json();
        
        // Only records below 0.6 should match
        $this->assertEquals(2, $data['meta']['total']);
        $this->assertEquals(0.6, $data['meta']['threshold']);
    }

    /** @test */
    public function it_filters_by_company_id()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company1->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.5,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company2->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.5,
        ]);

        $response = $this->getJson("/api/confidence?company_id={$company1->company_id}");

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals(1, $data['meta']['total']);
        $this->assertEquals($company1->company_id, $data['data'][0]['company_id']);
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
            'extraction_confidence_score' => 0.5,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'period_end_date' => '2024-09-30',
            'extraction_confidence_score' => 0.5,
        ]);

        $response = $this->getJson('/api/confidence?period_end_date=2024-12-31');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals(1, $data['meta']['total']);
        $this->assertEquals('2024-12-31', $data['data'][0]['period_end_date']);
    }

    /** @test */
    public function it_filters_by_metric()
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
            'extraction_confidence_score' => 0.5,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef2->metric_id,
            'extraction_confidence_score' => 0.5,
        ]);

        $response = $this->getJson('/api/confidence?metric=oil_production');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals(1, $data['meta']['total']);
    }

    /** @test */
    public function it_sorts_by_confidence_ascending_by_default()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.65,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.45,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.75,
        ]);

        $response = $this->getJson('/api/confidence');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should be sorted lowest to highest
        $this->assertEquals(0.45, $data[0]['extraction_confidence_score']);
        $this->assertEquals(0.65, $data[1]['extraction_confidence_score']);
        $this->assertEquals(0.75, $data[2]['extraction_confidence_score']);
    }

    /** @test */
    public function it_sorts_by_confidence_descending()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.65,
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.45,
        ]);

        $response = $this->getJson('/api/confidence?sort_by=confidence:desc');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should be sorted highest to lowest
        $this->assertEquals(0.65, $data[0]['extraction_confidence_score']);
        $this->assertEquals(0.45, $data[1]['extraction_confidence_score']);
    }

    /** @test */
    public function it_respects_limit_and_offset()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->count(10)->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.5,
        ]);

        $response = $this->getJson('/api/confidence?limit=5&offset=0');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertEquals(5, $data['meta']['limit']);
        $this->assertEquals(0, $data['meta']['offset']);
        $this->assertEquals(10, $data['meta']['total']);
        $this->assertCount(5, $data['data']);
    }

    /** @test */
    public function it_includes_review_hints()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.45,
            'extraction_method' => 'OCR',
        ]);

        $response = $this->getJson('/api/confidence');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertNotEmpty($data[0]['review_hint']);
        $this->assertStringContainsString('OCR', $data[0]['review_hint']);
    }

    /** @test */
    public function it_generates_appropriate_review_hints_for_different_methods()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        $testCases = [
            ['method' => 'OCR', 'expectedPhrase' => 'OCR'],
            ['method' => 'LLM', 'expectedPhrase' => 'AI/LLM'],
            ['method' => 'EGDAR', 'expectedPhrase' => 'XBRL/EDGAR'],
            ['method' => 'html_table_reader', 'expectedPhrase' => 'HTML table'],
        ];

        foreach ($testCases as $case) {
            $metric = MetricValue::factory()->create([
                'company_id' => $company->company_id,
                'metric_id' => $metricDef->metric_id,
                'extraction_confidence_score' => 0.5,
                'extraction_method' => $case['method'],
            ]);

            $response = $this->getJson('/api/confidence');
            $data = $response->json('data');
            
            $item = collect($data)->firstWhere('metric_value_id', $metric->metric_value_id);
            $this->assertNotNull($item);
            $this->assertStringContainsString($case['expectedPhrase'], $item['review_hint']);
        }
    }

    /** @test */
    public function it_performs_unit_conversion()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create([
            'metric_name_internal' => 'oil_production',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extracted_metric_value' => 5000,
            'extracted_metric_unit' => 'bbl',
            'extraction_confidence_score' => 0.5,
        ]);

        $response = $this->getJson('/api/confidence');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals(5000, $data[0]['original_value']);
        $this->assertEquals('bbl', $data[0]['original_unit']);
        $this->assertEquals(5, $data[0]['normalized_value']); // 5000 bbl = 5 mbbl
        $this->assertEquals('mbbl', $data[0]['normalized_unit']);
    }

    /** @test */
    public function it_returns_400_for_invalid_threshold()
    {
        $response = $this->getJson('/api/confidence?threshold=1.5');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid parameters'
            ]);
    }

    /** @test */
    public function it_includes_source_url_when_available()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();
        $sourceDoc = SourceDocument::factory()->create([
            'company_id' => $company->company_id,
            'source_url' => 'https://www.sec.gov/test',
        ]);

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'source_document_id' => $sourceDoc->source_document_id,
            'extraction_confidence_score' => 0.5,
        ]);

        $response = $this->getJson('/api/confidence');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals('https://www.sec.gov/test', $data[0]['source_url']);
    }

    /** @test */
    public function it_flags_critical_priority_for_very_low_confidence()
    {
        $company = Company::factory()->create();
        $metricDef = MetricDefinition::factory()->create();

        MetricValue::factory()->create([
            'company_id' => $company->company_id,
            'metric_id' => $metricDef->metric_id,
            'extraction_confidence_score' => 0.35, // Very low
        ]);

        $response = $this->getJson('/api/confidence');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertStringContainsString('Critical priority', $data[0]['review_hint']);
    }
}

