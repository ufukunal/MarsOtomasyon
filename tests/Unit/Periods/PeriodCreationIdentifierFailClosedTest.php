<?php

use App\Actions\Periods\CreatePeriod;
use App\Models\Company;

it('rejects malformed tenant database identifiers before any database connection or DDL', function (string $prefix): void {
    $company = new Company(['db_prefix' => $prefix]);
    $company->id = 1;

    expect(fn () => (new CreatePeriod)->handle($company, 2027))->toThrow(RuntimeException::class);
})->with([
    'SQL metacharacter' => ['mars;DROP_DATABASE'],
    'whitespace' => ['mars tenant'],
    'hyphen' => ['mars-prod'],
    'quote' => ['mars"owner'],
    'path traversal' => ['../private'],
    'leading number' => ['1tenant'],
]);
