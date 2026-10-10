<?php

use App\Actions\Locations\SaveLocation;
use App\Actions\ReferenceData\SaveUnitConversion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('rejects zero and same-unit conversion factors before persisting reference data', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $before = DB::connection('period')->table('unit_conversions')->count();
            foreach ([
                ['from_unit_id' => 1, 'to_unit_id' => 2, 'factor' => '0'],
                ['from_unit_id' => 1, 'to_unit_id' => 2, 'factor' => '-0.01'],
                ['from_unit_id' => 1, 'to_unit_id' => 1, 'factor' => '1'],
            ] as $bad) {
                expect(fn () => app(SaveUnitConversion::class)->handle($bad))
                    ->toThrow(ValidationException::class);
            }
            expect(DB::connection('period')->table('unit_conversions')->count())->toBe($before);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('requires vehicle plates and subcontractor contact references', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            foreach ([
                ['kind' => 'vehicle', 'name' => 'V4 Vehicle', 'plate' => ''],
                ['kind' => 'subcontractor', 'name' => 'V4 Fason'],
            ] as $bad) {
                expect(fn () => app(SaveLocation::class)->handle($bad))
                    ->toThrow(ValidationException::class);
            }
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
