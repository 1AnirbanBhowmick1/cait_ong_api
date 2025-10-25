<?php

namespace App\Services;

class UnitConverterService
{
    /**
     * Conversion rules for different metric types
     */
    private const CONVERSION_RULES = [
        // Volume conversions (oil)
        'volume' => [
            'bbl' => ['bbl' => 1, 'mbbl' => 0.001, 'mmbbl' => 0.000001],
            'mbbl' => ['bbl' => 1000, 'mbbl' => 1, 'mmbbl' => 0.001],
            'mmbbl' => ['bbl' => 1000000, 'mbbl' => 1000, 'mmbbl' => 1],
        ],
        
        // Gas conversions
        'gas' => [
            'mcf' => ['mcf' => 1, 'mmcf' => 0.001, 'bcf' => 0.000001],
            'mmcf' => ['mcf' => 1000, 'mmcf' => 1, 'bcf' => 0.001],
            'bcf' => ['mcf' => 1000000, 'mmcf' => 1000, 'bcf' => 1],
        ],
        
        // BOE conversions
        'boe' => [
            'boe' => ['boe' => 1, 'mboe' => 0.001, 'mmboe' => 0.000001],
            'mboe' => ['boe' => 1000, 'mboe' => 1, 'mmboe' => 0.001],
            'mmboe' => ['boe' => 1000000, 'mboe' => 1000, 'mmboe' => 1],
        ],
        
        // Length conversions
        'length' => [
            'ft' => ['ft' => 1, 'm' => 0.3048],
            'm' => ['ft' => 3.28084, 'm' => 1],
        ],
        
        // Price conversions (per barrel)
        'price_per_barrel' => [
            '$/bbl' => ['$/bbl' => 1],
            'usd/bbl' => ['$/bbl' => 1],
        ],
        
        // Price conversions (per mcf)
        'price_per_mcf' => [
            '$/mcf' => ['$/mcf' => 1],
            'usd/mcf' => ['$/mcf' => 1],
        ],
    ];
    
    /**
     * Metric to conversion type mapping
     */
    private const METRIC_CONVERSION_MAP = [
        'oil_production' => 'volume',
        'ngl_production' => 'volume',
        'gas_production' => 'gas',
        'boe_production' => 'boe',
        'total_lateral_length_drilled' => 'length',
        'total_lateral_length_completed' => 'length',
        'total_lateral_length_tiled' => 'length',
        'avg_realized_oil_price' => 'price_per_barrel',
        'avg_realized_gas_price' => 'price_per_mcf',
    ];
    
    /**
     * Standard units for each metric type
     */
    private const STANDARD_UNITS = [
        'volume' => 'mbbl',
        'gas' => 'mmcf',
        'boe' => 'mboe',
        'length' => 'ft',
        'price_per_barrel' => '$/bbl',
        'price_per_mcf' => '$/mcf',
        'count' => '#',
        'percentage' => '%',
        'dollars' => '$000',
    ];
    
    /**
     * Convert a value from one unit to another
     *
     * @param float|null $value
     * @param string|null $fromUnit
     * @param string $metricInternalName
     * @return array ['value' => float|null, 'unit' => string]
     */
    public function convert($value, $fromUnit, string $metricInternalName): array
    {
        // If value is null or empty, return as-is
        if (is_null($value) || $value === '' || is_null($fromUnit) || $fromUnit === '') {
            return ['value' => $value, 'unit' => $fromUnit];
        }
        
        // Normalize units
        $fromUnit = $this->normalizeUnit($fromUnit);
        
        // Determine conversion type
        $conversionType = $this->getConversionType($metricInternalName, $fromUnit);
        
        // If no conversion needed (e.g., counts, percentages)
        if (!$conversionType) {
            return ['value' => $value, 'unit' => $fromUnit];
        }
        
        // Get standard unit for this type
        $toUnit = self::STANDARD_UNITS[$conversionType] ?? $fromUnit;
        
        // If already in standard unit, no conversion needed
        if ($fromUnit === $toUnit) {
            return ['value' => $value, 'unit' => $toUnit];
        }
        
        // Perform conversion
        $convertedValue = $this->performConversion($value, $fromUnit, $toUnit, $conversionType);
        
        return [
            'value' => $convertedValue,
            'unit' => $toUnit
        ];
    }
    
    /**
     * Normalize unit strings
     */
    private function normalizeUnit(string $unit): string
    {
        $unit = strtolower(trim($unit));
        
        // Handle common variations
        $normalizations = [
            'barrel' => 'bbl',
            'barrels' => 'bbl',
            'mbarrel' => 'mbbl',
            'mmbarrel' => 'mmbbl',
            'feet' => 'ft',
            'meters' => 'm',
            'meter' => 'm',
            'usd' => '$',
            'wells' => '#',
            'well' => '#',
            'percent' => '%',
        ];
        
        return $normalizations[$unit] ?? $unit;
    }
    
    /**
     * Determine conversion type based on metric name and unit
     */
    private function getConversionType(string $metricInternalName, string $unit): ?string
    {
        // Check if metric has a predefined conversion type
        if (isset(self::METRIC_CONVERSION_MAP[$metricInternalName])) {
            return self::METRIC_CONVERSION_MAP[$metricInternalName];
        }
        
        // Infer from unit
        if (in_array($unit, ['bbl', 'mbbl', 'mmbbl'])) {
            return 'volume';
        }
        
        if (in_array($unit, ['mcf', 'mmcf', 'bcf'])) {
            return 'gas';
        }
        
        if (in_array($unit, ['boe', 'mboe', 'mmboe'])) {
            return 'boe';
        }
        
        if (in_array($unit, ['ft', 'm'])) {
            return 'length';
        }
        
        if (in_array($unit, ['#', 'wells'])) {
            return 'count';
        }
        
        if (in_array($unit, ['%', 'percent'])) {
            return 'percentage';
        }
        
        if (strpos($unit, '$') !== false) {
            if (strpos($unit, 'bbl') !== false) {
                return 'price_per_barrel';
            }
            if (strpos($unit, 'mcf') !== false) {
                return 'price_per_mcf';
            }
            return 'dollars';
        }
        
        return null;
    }
    
    /**
     * Perform the actual conversion
     */
    private function performConversion($value, string $fromUnit, string $toUnit, string $conversionType): ?float
    {
        if (!isset(self::CONVERSION_RULES[$conversionType])) {
            return $value;
        }
        
        $rules = self::CONVERSION_RULES[$conversionType];
        
        // Check if conversion is defined
        if (isset($rules[$fromUnit][$toUnit])) {
            $factor = $rules[$fromUnit][$toUnit];
            return round($value * $factor, 6);
        }
        
        // If no direct conversion, return original value
        return $value;
    }
    
    /**
     * Get display value (prefer normalized, fallback to original)
     */
    public function getDisplayValue($originalValue, $originalUnit, $normalizedValue, $normalizedUnit): array
    {
        if (!is_null($normalizedValue) && $normalizedValue !== '') {
            return [
                'value' => $normalizedValue,
                'unit' => $normalizedUnit ?? $originalUnit
            ];
        }
        
        return [
            'value' => $originalValue,
            'unit' => $originalUnit
        ];
    }
}

