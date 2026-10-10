<?php

use App\Support\Reporting\ReportColumnDefinition;
use App\Support\Reporting\ReportDefinition;
use App\Support\Reporting\ReportEngine;
use Illuminate\Auth\Access\AuthorizationException;

function marsSensitiveReportDefinition(): ReportDefinition
{
    return new ReportDefinition(
        key: 'v4.sensitive',
        title: 'V4 Sensitive Report',
        category: 'finance',
        permission: 'reports.view',
        filters: [],
        columns: [
            new ReportColumnDefinition('number', 'Number'),
            new ReportColumnDefinition('unit_cost', 'Cost', 'money', true, true, true),
            new ReportColumnDefinition('description', 'Description', 'text', false),
        ],
        defaultSort: [],
        exporters: ['screen', 'csv'],
    );
}

it('allows explicit cost columns only with cost.view permission', function (): void {
    $engine = (new ReflectionClass(ReportEngine::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ReportEngine::class, 'normalizeColumns');
    $definition = marsSensitiveReportDefinition();

    expect(fn () => $method->invoke($engine, $definition, ['unit_cost'], false))
        ->toThrow(AuthorizationException::class);
    expect($method->invoke($engine, $definition, ['number', 'number'], false))
        ->toBe(['number']);
    expect($method->invoke($engine, $definition, ['number', 'unit_cost'], true))
        ->toBe(['number', 'unit_cost']);
});

it('prevents cost-based sort side channels when a user lacks cost permissions', function (): void {
    $engine = (new ReflectionClass(ReportEngine::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ReportEngine::class, 'normalizeSort');
    $definition = marsSensitiveReportDefinition();

    expect(fn () => $method->invoke($engine, $definition, [
        ['key' => 'unit_cost', 'direction' => 'desc'],
    ], false))->toThrow(AuthorizationException::class);

    expect(fn () => $method->invoke($engine, $definition, [
        ['key' => 'description', 'direction' => 'asc'],
    ], true))->toThrow(DomainException::class);
});

it('rejects unregistered report filters before constructing a database query', function (): void {
    $engine = (new ReflectionClass(ReportEngine::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ReportEngine::class, 'normalizeFilters');

    expect(fn () => $method->invoke($engine, marsSensitiveReportDefinition(), [
        'drop_table' => 'unexpected',
    ]))->toThrow(DomainException::class);
});
