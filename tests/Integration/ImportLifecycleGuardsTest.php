<?php

use App\Actions\Imports\CloseImportFile;
use App\Actions\Imports\ReceiveImportFile;
use App\Actions\Imports\SaveImportCostItem;
use App\Models\Period\ImportFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

function marsV4ImportFile(string $status = 'draft', string $currency = 'USD'): ImportFile
{
    $db = DB::connection('period');
    $contactId = $db->table('contacts')->insertGetId([
        'title' => 'V4 import supplier',
        'type' => 'legal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ImportFile::query()->create([
        'number' => 'IMP-V4-'.Str::random(12),
        'supplier_contact_id' => $contactId,
        'currency' => $currency,
        'exchange_rate' => $currency === 'TRY' ? '1.000000' : '35.000000',
        'status' => $status,
    ]);
}

it('rejects received imports without a locked exchange rate and complete packages', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $file = marsV4ImportFile('received');
            expect(fn () => app(CloseImportFile::class)->handle($file, 'v4-'.Str::random(20)))
                ->toThrow(DomainException::class);
            expect($file->refresh()->status)->toBe('received');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects importing a shipment with no cartons', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $file = marsV4ImportFile('in_transit');
            expect(fn () => app(ReceiveImportFile::class)->handle(
                $file, '2026-10-10', 'v4-'.Str::random(20),
            ))->toThrow(DomainException::class);
            expect($file->refresh()->status)->toBe('in_transit');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects unsupported allocation bases and negative landed-cost rows without inserting them', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $file = marsV4ImportFile();
            $action = app(SaveImportCostItem::class);
            $valid = ['name' => 'Freight', 'amount' => '100', 'currency' => 'TRY'];

            expect(fn () => $action->handle($file, [...$valid, 'allocation_basis' => 'unsafe']))
                ->toThrow(DomainException::class);
            expect(fn () => $action->handle($file, [...$valid, 'amount' => '-1']))
                ->toThrow(DomainException::class);
            expect(fn () => $action->handle($file, [...$valid, 'currency' => 'US']))
                ->toThrow(DomainException::class);

            expect(DB::connection('period')->table('import_cost_items')
                ->where('import_file_id', $file->id)->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
