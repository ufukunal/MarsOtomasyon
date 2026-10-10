<?php

use App\Models\Company;
use App\Models\Period;
use App\Models\User;

it('keeps master entities on master connection', function (): void {
    expect((new Company)->getConnectionName())->toBe('master')
        ->and((new Period)->getConnectionName())->toBe('master')
        ->and((new User)->getConnectionName())->toBe('master');
});

it('formats period database names from the company prefix and year', function (): void {
    $period = new Period(['year' => 2026]);
    $period->setRelation('company', new Company(['db_prefix' => 'mars_demo']));
    expect($period->databaseName())->toBe('mars_demo_2026');
});
