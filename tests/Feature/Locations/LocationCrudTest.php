<?php

use App\Actions\Locations\SaveLocation;
use App\Models\Period\Location;
use Illuminate\Validation\ValidationException;

it('depo şube ve araç lokasyonlarını açar ve varsayılanı tekil tutar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('LOCATION');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $action = app(SaveLocation::class);

    $warehouse = $action->handle([
        'code' => 'MERKEZ',
        'name' => 'Merkez Depo',
        'kind' => 'warehouse',
        'is_default' => true,
        'is_active' => true,
    ]);

    $branch = $action->handle([
        'code' => 'SUBE',
        'name' => 'Şube',
        'kind' => 'branch',
        'is_default' => false,
        'is_active' => true,
    ]);

    $vehicle = $action->handle([
        'code' => 'ARAC',
        'name' => 'Sıcak Satış Aracı',
        'kind' => 'vehicle',
        'plate' => '34 abc 123',
        'is_default' => true,
        'is_active' => true,
    ]);

    expect(Location::query()->count())->toBe(3)
        ->and($warehouse->refresh()->is_default)->toBeFalse()
        ->and($branch->kind->value)->toBe('branch')
        ->and($vehicle->kind->value)->toBe('vehicle')
        ->and($vehicle->plate)->toBe('34 ABC 123')
        ->and($vehicle->is_default)->toBeTrue();
});

it('araç lokasyonunda plakayı zorunlu tutar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('LOCPLATE');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    app(SaveLocation::class)->handle([
        'code' => 'MERKEZ',
        'name' => 'Merkez Depo',
        'kind' => 'warehouse',
        'is_default' => true,
        'is_active' => true,
    ]);

    expect(fn () => app(SaveLocation::class)->handle([
        'code' => 'ARAC',
        'name' => 'Plakasız Araç',
        'kind' => 'vehicle',
        'plate' => '',
        'is_default' => false,
        'is_active' => true,
    ]))->toThrow(ValidationException::class);
});
