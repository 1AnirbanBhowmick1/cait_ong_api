<?php

namespace Database\Factories;

use App\Models\SourceDocument;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class SourceDocumentFactory extends Factory
{
    protected $model = SourceDocument::class;

    public function definition(): array
    {
        $filingTypes = ['10-K', '10-Q', '8-K'];
        $fileFormats = ['PDF', 'HTML', 'XBRL'];
        
        return [
            'company_id' => Company::factory(),
            'source_type' => 'SEC_FILING',
            'filing_type' => fake()->randomElement($filingTypes),
            'filing_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'period_end_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'source_url' => 'https://www.sec.gov/Archives/edgar/data/' . fake()->randomNumber(8) . '.htm',
            'raw_text_blob_path' => null,
            'file_format' => fake()->randomElement($fileFormats),
            'extraction_confidence_score' => fake()->randomFloat(2, 0.85, 0.99),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

