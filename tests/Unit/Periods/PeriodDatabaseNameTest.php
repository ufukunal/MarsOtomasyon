<?php

use App\Models\Company;
use App\Models\Period;

it('derives isolated database names for multiple company/year combinations', function (): void {
    foreach ([
        ['mars_a', 2025, 'mars_a_2025'],
        ['mars_a', 2026, 'mars_a_2026'],
        ['mars_b', 2026, 'mars_b_2026'],
    ] as [$prefix, $year, $expected]) {
        $period = new Period(['year' => $year]);
        $period->setRelation('company', new Company(['db_prefix' => $prefix]));
        expect($period->databaseName())->toBe($expected);
    }
});
