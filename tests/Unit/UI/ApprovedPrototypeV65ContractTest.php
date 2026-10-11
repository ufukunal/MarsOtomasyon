<?php

use Illuminate\Support\Facades\Route;

it('keeps all v65 sidebar destinations real and authorization guarded', function (): void {
    $groups = config('navigation');
    $expected = [
        'Ana Sayfa', 'Kişiler / Firmalar', 'Ürün / Stok',
        'Satış Yönetimi', 'Ambar', 'Satınalma Yönetimi',
        'Finans İşlemleri', 'İade / RMA', 'İthalat', 'Üretim',
        'E-Ticaret', 'Raporlar', 'Ayarlar',
    ];

    expect(array_column($groups, 'label'))->toBe($expected);

    $routes = [];
    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            expect(Route::has($item['route']))
                ->toBeTrue("v65 menü bağlantısı bulunamadı: {$item['route']}");
            expect($item['permission'] !== null || $item['route'] === 'home')
                ->toBeTrue("Yetki bilgisi eksik: {$item['route']}");
            $routes[] = $item['route'];
        }
    }

    expect(count($routes))->toBeGreaterThanOrEqual(60)
        ->and(count(array_unique($routes)))->toBe(count($routes));
});

it('preserves the canonical v65 theme in the single shared stylesheet', function (): void {
    $styles = file_get_contents(base_path('resources/css/app.css'));

    expect(strlen($styles))->toBeLessThan(40000)
        ->and($styles)->not->toContain('!important')
        ->and($styles)->toContain('--side:238px')
        ->and($styles)->toContain('.nav-group-title')
        ->and($styles)->toContain('.document-editor-form')
        ->and($styles)->toContain('.wizard-progress-track');

    expect(glob(base_path('resources/css/*.css')))->toHaveCount(1);
    expect(is_file(base_path('reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html')))->toBeTrue();
});

it('labels all wizard default settings and protects full editor form bindings', function (): void {
    $wizard = file_get_contents(base_path('resources/views/livewire/pages/setup/company-wizard.blade.php'));

    foreach (['defaultTermDays', 'costDeviationThreshold', 'defaultVatRate',
        'warehouseCode', 'warehouseName', 'dbPrefix', 'adminEmail'] as $field) {
        expect($wizard)->toContain('wire:model="'.$field.'"');
    }

    expect($wizard)->toContain('Varsayılan Vade Günü')
        ->toContain('Maliyet Sapma Eşiği')
        ->toContain('Varsayılan KDV Oranı')
        ->toContain('role="alert"');

    foreach (['sales', 'purchases'] as $module) {
        $view = file_get_contents(base_path("resources/views/livewire/{$module}/document-editor.blade.php"));
        expect($view)->toContain('class="document-editor-form"')
            ->toContain('wire:submit="save"')
            ->toContain('class="table-scroll document-lines"');
    }
});

it('does not duplicate company selector in top bar and provides real sign-out action', function (): void {
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $switcher = file_get_contents(base_path('resources/views/livewire/components/period-switcher.blade.php'));

    expect($layout)->toContain("route('logout')")
        ->toContain('id="global-screen-search"')
        ->and($switcher)->not->toContain('wire:model.live="companyId"');
});
