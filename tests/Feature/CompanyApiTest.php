<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CompanyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** @test */
    public function it_returns_list_of_companies_with_default_parameters()
    {
        // Create test companies
        Company::factory()->count(3)->create(['status' => true]);
        Company::factory()->count(2)->create(['status' => false]);

        $response = $this->getJson('/api/companies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'meta' => ['limit', 'offset', 'total'],
                'data' => [
                    '*' => [
                        'company_id',
                        'company_name',
                        'ticker_symbol',
                        'company_type',
                        'status',
                        'created_at',
                    ],
                ],
            ]);

        // Default active_only=true should only return active companies
        $this->assertEquals(3, $response->json('meta.total'));
    }

    /** @test */
    public function it_returns_all_companies_when_active_only_is_false()
    {
        Company::factory()->count(3)->create(['status' => true]);
        Company::factory()->count(2)->create(['status' => false]);

        $response = $this->getJson('/api/companies?active_only=false');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.total'));
    }

    /** @test */
    public function it_filters_companies_by_search_term()
    {
        Company::factory()->create([
            'company_name' => 'Diamondback Energy, Inc.',
            'ticker_symbol' => 'FANG',
            'status' => true,
        ]);

        Company::factory()->create([
            'company_name' => 'Exxon Mobil Corporation',
            'ticker_symbol' => 'XOM',
            'status' => true,
        ]);

        // Search by company name
        $response = $this->getJson('/api/companies?search=Diamondback');
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertStringContainsString('Diamondback', $response->json('data.0.company_name'));

        // Search by ticker symbol
        $response = $this->getJson('/api/companies?search=XOM');
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('XOM', $response->json('data.0.ticker_symbol'));
    }

    /** @test */
    public function it_respects_limit_and_offset_parameters()
    {
        Company::factory()->count(10)->create(['status' => true]);

        $response = $this->getJson('/api/companies?limit=5&offset=0');
        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.limit'));
        $this->assertEquals(0, $response->json('meta.offset'));
        $this->assertEquals(10, $response->json('meta.total'));
        $this->assertCount(5, $response->json('data'));

        $response = $this->getJson('/api/companies?limit=5&offset=5');
        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function it_returns_400_for_invalid_parameters()
    {
        $response = $this->getJson('/api/companies?limit=-5');
        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid parameters',
            ]);

        $response = $this->getJson('/api/companies?offset=-1');
        $response->assertStatus(400);

        $response = $this->getJson('/api/companies?active_only=invalid');
        $response->assertStatus(400);
    }

    /** @test */
    public function it_orders_companies_by_name()
    {
        Company::factory()->create(['company_name' => 'Zebra Company', 'status' => true]);
        Company::factory()->create(['company_name' => 'Alpha Company', 'status' => true]);
        Company::factory()->create(['company_name' => 'Beta Company', 'status' => true]);

        $response = $this->getJson('/api/companies');

        $response->assertStatus(200);
        $companies = $response->json('data');
        $this->assertEquals('Alpha Company', $companies[0]['company_name']);
        $this->assertEquals('Beta Company', $companies[1]['company_name']);
        $this->assertEquals('Zebra Company', $companies[2]['company_name']);
    }

    /** @test */
    public function it_caches_results_for_5_minutes()
    {
        Company::factory()->create(['company_name' => 'Test Company', 'status' => true]);

        // First request
        $response1 = $this->getJson('/api/companies');
        $response1->assertStatus(200);

        // Delete the company
        Company::where('company_name', 'Test Company')->delete();

        // Second request should still return cached result
        $response2 = $this->getJson('/api/companies');
        $response2->assertStatus(200);
        $this->assertEquals(1, $response2->json('meta.total'));

        // Clear cache
        Cache::flush();

        // Third request should reflect the deletion
        $response3 = $this->getJson('/api/companies');
        $response3->assertStatus(200);
        $this->assertEquals(0, $response3->json('meta.total'));
    }
}
