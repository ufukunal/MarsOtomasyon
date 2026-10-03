<?php

namespace App\Actions\ReferenceData;

use App\Models\Period\Unit;
use App\Support\Period\PeriodContext;

final class SeedPeriodReferenceData
{
    public function handle(): void
    {
        PeriodContext::ensure();

        $units = [
            ['code' => 'ADET', 'name' => 'Adet', 'is_base' => true],
            ['code' => 'KUTU', 'name' => 'Kutu', 'is_base' => false],
            ['code' => 'KOLI', 'name' => 'Koli', 'is_base' => false],
            ['code' => 'KG', 'name' => 'Kilogram', 'is_base' => false],
            ['code' => 'METRE', 'name' => 'Metre', 'is_base' => false],
            ['code' => 'SET', 'name' => 'Set', 'is_base' => false],
        ];

        foreach ($units as $unit) {
            Unit::query()->firstOrCreate(['code' => $unit['code']], [
                ...$unit,
                'is_active' => true,
            ]);
        }
    }
}
