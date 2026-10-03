<?php

namespace Database\Seeders;

use App\Actions\Periods\CreatePeriod;
use App\Models\Company;
use App\Models\Period;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'code' => 'ABCHOLDING',
                'name' => 'ABC Holding',
                'db_prefix' => 'ABCHolding',
            ],
            [
                'code' => 'XYZLTD',
                'name' => 'XYZ Ltd. Şti.',
                'db_prefix' => 'XYZltd',
            ],
        ];

        foreach ($companies as $attributes) {
            $company = Company::query()->firstOrCreate(
                ['code' => $attributes['code']],
                $attributes,
            );

            if (! Period::query()->where('company_id', $company->id)->where('year', 2026)->exists()) {
                app(CreatePeriod::class)->handle($company, 2026);
            }
        }
    }
}
