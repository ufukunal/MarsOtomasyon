<?php

use App\Support\Reporting\MultiPeriod\MultiPeriodQuery;

it('decodes allowed period permission overrides from JSON or already-parsed arrays', function (): void {
    $query = (new ReflectionClass(MultiPeriodQuery::class))->newInstanceWithoutConstructor();
    $decode = new ReflectionMethod(MultiPeriodQuery::class, 'decodeOverrides');
    $overrides = ['allow' => ['reports.view'], 'deny' => ['costs.view']];

    expect($decode->invoke($query, json_encode($overrides, JSON_THROW_ON_ERROR)))->toBe($overrides)
        ->and($decode->invoke($query, $overrides))->toBe($overrides);
});

it('fails closed to an empty override set on malformed, null or unexpected access metadata', function (mixed $input): void {
    $query = (new ReflectionClass(MultiPeriodQuery::class))->newInstanceWithoutConstructor();

    expect((new ReflectionMethod(MultiPeriodQuery::class, 'decodeOverrides'))->invoke($query, $input))
        ->toBe([]);
})->with([
    'null' => [null],
    'invalid JSON' => ['{not-json'],
    'empty text' => [''],
    'integer' => [99],
    'object' => [new stdClass],
]);
