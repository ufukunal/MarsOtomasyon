<?php

use Illuminate\Support\Facades\Route;
use Livewire\Component;

it('registers only concrete Livewire components as business screen handlers', function (): void {
    $checked = 0;
    foreach (Route::getRoutes() as $route) {
        $handler = $route->getActionName();
        if (! str_starts_with($handler, 'App\\Livewire\\')) {
            continue;
        }
        expect(class_exists($handler))->toBeTrue("Missing routed screen: {$handler}");
        expect(is_subclass_of($handler, Component::class))->toBeTrue("Not a Livewire component: {$handler}");
        expect((new ReflectionClass($handler))->isAbstract())->toBeFalse();
        $checked++;
    }
    expect($checked)->toBeGreaterThan(40);
});

it('includes production, purchasing, sales, finance, imports, reporting and channel screens', function (): void {
    $names = [
        'production.recipes', 'production.orders', 'production.subcontracting',
        'purchases.orders.index', 'sales.orders.index', 'finance.operations',
        'imports.shipments', 'returns.center', 'channels.accounts',
        'reports.center', 'stock.status', 'settings.period-carry',
    ];
    foreach ($names as $name) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull()
            ->and($route->methods())->toContain('GET');
    }
});
