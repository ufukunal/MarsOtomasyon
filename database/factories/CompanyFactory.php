<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('######');

        return [
            'code' => 'T'.$suffix,
            'name' => 'Test Şirket '.$suffix,
            'db_prefix' => 'TST'.$suffix,
            'default_term_days' => 30,
            'cost_deviation_threshold' => '25.0000',
            'base_currency' => 'TRY',
            'is_active' => true,
        ];
    }
}
