<?php

use App\Actions\Imports\RecalculateImportCosts;
use App\Actions\Imports\SaveImportContainer;
use App\Actions\Imports\SaveImportFile;
use App\Actions\Imports\SaveImportPackage;
use Illuminate\Support\Facades\DB;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('persists a draft import container and matched carton but refuses invalid quantity and volume', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $contactId = DB::connection('period')->table('contacts')->insertGetId([
                'title' => 'V4 Import Supplier',
                'type' => 'legal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $file = app(SaveImportFile::class)->handle([
                'supplier_contact_id' => $contactId,
                'currency' => 'TRY',
                'status' => 'draft',
            ]);

            $container = app(SaveImportContainer::class)->handle($file, [
                'container_no' => 'V4-CONT-1',
                'gross_weight_kg' => '100.000',
                'volume_cbm' => '20.0000',
            ]);

            expect($container->container_no)->toBe('V4-CONT-1');

            expect(fn () => app(SaveImportContainer::class)->handle(
                $file, ['container_no' => 'V4-CONT-2', 'gross_weight_kg' => '-1'],
            ))->toThrow(DomainException::class);

            $packages = app(SaveImportPackage::class);
            $input = [
                'container_id' => $container->id,
                'carton_no' => 'CARTON-V4',
                'product_id' => $ids['product'],
                'quantity' => '3.000',
                'unit_price' => '4.0000',
                'weight_kg' => '9.0000',
                'location_id' => $ids['location'],
            ];

            foreach ([
                ['quantity' => '0'],
                ['unit_price' => '-0.0001'],
                ['weight_kg' => '-1'],
                ['carton_no' => ' '],
            ] as $bad) {
                expect(fn () => $packages->handle($file, [...$input, ...$bad]))
                    ->toThrow(DomainException::class);
            }

            $carton = $packages->handle($file, $input);
            expect($carton->status)->toBe('matched')
                ->and((string) $carton->quantity)->toBe('3.000');

            expect(DB::connection('period')->table('packages')
                ->where('import_file_id', $file->id)->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects import cost recalculation after an import file is received', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $contactId = DB::connection('period')->table('contacts')->insertGetId([
                'title' => 'V4 Import Supplier',
                'type' => 'legal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $file = app(SaveImportFile::class)->handle([
                'supplier_contact_id' => $contactId, 'currency' => 'TRY',
            ]);
            $file->status = 'received';
            $file->save();

            expect(fn () => app(RecalculateImportCosts::class)->handle($file, 'v4-import-recalc-locked'))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
