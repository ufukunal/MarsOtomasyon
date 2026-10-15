<?php

use App\Livewire\Pages\Catalog\BrandList;
use App\Livewire\Pages\Catalog\UnitList;
use Illuminate\Support\Facades\Route;

it('exposes the existing brand and unit create/edit routes', function (): void {
    foreach (['brands.index', 'brands.create', 'brands.edit', 'units.index', 'units.create', 'units.edit'] as $route) {
        expect(Route::has($route))->toBeTrue();
    }
});

it('makes catalog create actions and identifier links permission-scoped', function (): void {
    foreach ([BrandList::class => ['brands.create', 'brands.update', 'brands.edit'], UnitList::class => ['units.create', 'units.update', 'units.edit']] as $class => $spec) {
        $source = file_get_contents(base_path(str_replace('App/', 'app/', str_replace('\\', '/', $class)).'.php'));
        // The list must not expose mutations to users who cannot create/update.
        expect($source)->toContain("can('".$spec[0]."')")
            ->toContain("can('".$spec[1]."')")
            ->toContain("route('".$spec[2]."'")
            ->toContain("redirectRoute('".$spec[2]."'")
            ->toContain('function emptyAction')
            ->toContain('function rowActions')
            ->toContain('return [];')
            ->toContain('function columnUrl');
    }
});

it('adds create actions to the list header but opens records from identifiers', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    expect($view)->toContain('!$isProductTable && $emptyAction')
        ->toContain("@teleport('.page-actions.v65-page-actions')")
        ->toContain('route($emptyAction[\'route\'])')
        ->toContain('v65-record-link')
        ->toContain('$this->columnUrl($row, $column)');
});

it('provides navigation, save and unit conversion controls on catalog edit forms', function (): void {
    $brand = file_get_contents(base_path('resources/views/livewire/pages/catalog/brand-form.blade.php'));
    $unit = file_get_contents(base_path('resources/views/livewire/pages/catalog/unit-form.blade.php'));
    expect($brand)->toContain("route('brands.index')")
        ->toContain('wire:click="save"')
        ->and($unit)->toContain("route('units.index')")
        ->toContain('wire:click="save"')
        ->toContain('wire:click="addConversion"')
        ->toContain(':disabled="(bool) $unit"');
});
