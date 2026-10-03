<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Period>
 */
class PeriodFactory extends Factory
{
    protected $model = Period::class;

    public function definition(): array
    {
        $year = 2026;
        $token = fake()->unique()->numerify('######');

        return [
            'company_id' => Company::factory(),
            'year' => $year,
            'database_name' => "TST_FACTORY_{$token}_{$year}",
            'starts_on' => "{$year}-01-01",
            'ends_on' => "{$year}-12-31",
            'status' => 'active',
        ];
    }
}
