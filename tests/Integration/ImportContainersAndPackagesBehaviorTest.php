<?php

use App\Actions\Imports\SaveImportContainer;
use App\Actions\Imports\SaveImportFile;
use App\Actions\Imports\SaveImportPackage;
use App\Models\Period\Contact;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

function marsV4TestImportShipment(): \App\Models\Period\ImportFile
{
    $supplier = Contact::query()->create(['title' => 'V4 local shipment supplier', 'type' => 'legal']);

    return app(SaveImportFile::class)->handle([
        'supplier_contact_id' => $supplier->id,
        'currency' => 'USD',
        'exchange_rate' => '34.500000',
        'status' => 'draft',
    ]);
}

it('rejects supplier-less, invalid-currency and negative-FX import file drafts', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $action = app(SaveImportFile::class);
            foreach ([
                ['supplier_contact_id' => 0, 'currency' => 'USD', 'exchange_rate' => '35'],
                ['supplier_contact_id' => 1, 'currency' => 'US', 'exchange_rate' => '35'],
                ['supplier_contact_id' => 1, 'currency' => 'USD', 'exchange_rate' => '-1'],
            ] as $invalid) {
                expect(fn () => $action->handle($invalid))->toThrow(DomainException::class);
            }
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('stores a container and rejects missing identifiers or negative weights', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $file = marsV4TestImportShipment();
            $action = app(SaveImportContainer::class);
            $container = $action->handle($file, [
                'container_no' => 'V4-'.Str::random(8),
                'gross_weight_kg' => '123.000',
                'volume_cbm' => '7.5000',
            ]);

            expect((int) $container->import_file_id)->toBe((int) $file->id)
                ->and((string) $container->gross_weight_kg)->toBe('123.000');

            expect(fn () => $action->handle($file, [
                'container_no' => '',
            ]))->toThrow(DomainException::class);

            expect(fn () => $action->handle($file, [
                'container_no' => 'V4-BAD',
                'gross_weight_kg' => '-0.001',
            ]))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('requires positive package quantities, valid carton codes and nonnegative prices', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $file = marsV4TestImportShipment();
            $container = app(SaveImportContainer::class)->handle($file, [
                'container_no' => 'V4-'.Str::random(8),
            ]);
            $action = app(SaveImportPackage::class);
            $default = [
                'container_id' => $container->id,
                'carton_no' => 'V4-BOX-1',
                'quantity' => '2.000',
                'unit_price' => '5.0000',
            ];

            foreach ([
                ['quantity' => '0'],
                ['quantity' => '-1'],
                ['unit_price' => '-0.01'],
                ['carton_no' => ''],
                ['weight_kg' => '-1'],
            ] as $invalid) {
                expect(fn () => $action->handle($file, [...$default, ...$invalid]))
                    ->toThrow(DomainException::class);
            }

            $package = $action->handle($file, $default);
            expect((int) $package->container_id)->toBe((int) $container->id)
                ->and((string) $package->quantity)->toBe('2.000')
                ->and($package->status)->toBe('unmatched');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
