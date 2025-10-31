<?php

namespace Tests\Unit;

use App\Services\UnitConverterService;
use Tests\TestCase;

class UnitConverterServiceTest extends TestCase
{
    protected $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new UnitConverterService;
    }

    /** @test */
    public function it_converts_barrels_to_mbarrels()
    {
        $result = $this->converter->convert(1000, 'bbl', 'oil_production');

        $this->assertEquals(1, $result['value']);
        $this->assertEquals('mbbl', $result['unit']);
    }

    /** @test */
    public function it_converts_mbarrels_to_mbarrels()
    {
        $result = $this->converter->convert(100, 'mbbl', 'oil_production');

        $this->assertEquals(100, $result['value']);
        $this->assertEquals('mbbl', $result['unit']);
    }

    /** @test */
    public function it_converts_mcf_to_mmcf()
    {
        $result = $this->converter->convert(1000, 'mcf', 'gas_production');

        $this->assertEquals(1, $result['value']);
        $this->assertEquals('mmcf', $result['unit']);
    }

    /** @test */
    public function it_converts_feet_to_feet()
    {
        $result = $this->converter->convert(5000, 'ft', 'total_lateral_length_drilled');

        $this->assertEquals(5000, $result['value']);
        $this->assertEquals('ft', $result['unit']);
    }

    /** @test */
    public function it_handles_null_values()
    {
        $result = $this->converter->convert(null, 'mbbl', 'oil_production');

        $this->assertNull($result['value']);
    }

    /** @test */
    public function it_handles_count_units()
    {
        $result = $this->converter->convert(27, '#', 'gross_wells_drilled');

        $this->assertEquals(27, $result['value']);
        $this->assertEquals('#', $result['unit']);
    }

    /** @test */
    public function it_handles_percentage_units()
    {
        $result = $this->converter->convert(45.5, '%', 'working_interest_percentage');

        $this->assertEquals(45.5, $result['value']);
        $this->assertEquals('%', $result['unit']);
    }

    /** @test */
    public function it_normalizes_unit_names()
    {
        $result = $this->converter->convert(1000, 'barrels', 'oil_production');

        $this->assertEquals(1, $result['value']);
        $this->assertEquals('mbbl', $result['unit']);
    }

    /** @test */
    public function it_handles_boe_conversions()
    {
        $result = $this->converter->convert(1000, 'boe', 'boe_production');

        $this->assertEquals(1, $result['value']);
        $this->assertEquals('mboe', $result['unit']);
    }

    /** @test */
    public function it_converts_mmbbl_to_mbbl()
    {
        $result = $this->converter->convert(2.5, 'mmbbl', 'oil_production');

        $this->assertEquals(2500, $result['value']);
        $this->assertEquals('mbbl', $result['unit']);
    }

    /** @test */
    public function it_gets_display_value_preferring_normalized()
    {
        $result = $this->converter->getDisplayValue(1000, 'bbl', 1, 'mbbl');

        $this->assertEquals(1, $result['value']);
        $this->assertEquals('mbbl', $result['unit']);
    }

    /** @test */
    public function it_gets_display_value_fallback_to_original()
    {
        $result = $this->converter->getDisplayValue(1000, 'bbl', null, null);

        $this->assertEquals(1000, $result['value']);
        $this->assertEquals('bbl', $result['unit']);
    }
}
