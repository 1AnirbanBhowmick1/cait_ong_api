<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyTypes = ['upstream', 'integrated', 'midstream', 'downstream'];

        return [
            'company_name' => fake()->company(),
            'ticker_symbol' => strtoupper(fake()->lexify('????')),
            'sec_cik_number' => str_pad(fake()->randomNumber(8, true), 10, '0', STR_PAD_LEFT),
            'company_type' => fake()->randomElement($companyTypes),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the company is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    /**
     * Indicate that the company is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => true,
        ]);
    }
}
