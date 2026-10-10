<?php

use App\Actions\Imports\RecalculateImportCosts;
use App\Actions\Imports\SaveImportContainer;
use App\Actions\Imports\SaveImportCostItem;
use App\Actions\Imports\SaveImportFile;
use App\Actions\Imports\SaveImportPackage;
use App\Models\Period\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

function v4RoundingImport(int $count = 3, string $basis = 'value'): array
{
    $ids = IsolatedPostgres::productAndLocation();
    $supplier = Contact::query()->create(['title' => 'V4 Import Cost Supplier', 'type' => 'legal']);
    $file = app(SaveImportFile::class)->handle([
        'supplier_contact_id' => $supplier->id,
        'currency' => 'TRY',
        'exchange_rate' => '1.000000',
        'status' => 'draft',
    ]);
    $container = app(SaveImportContainer::class)->handle($file, [
        'container_no' => 'V4-'.Str::random(9),
    ]);

    $packages = [];
    for ($i = 0; $i < $count; $i++) {
        $packages[] = app(SaveImportPackage::class)->handle($file, [
            'container_id' => $container->id,
            'carton_no' => "V4-BOX-{$i}",
            'product_id' => $ids['product'],
            'location_id' => $ids['location'],
            'quantity' => '1.000',
            'unit_price' => '10.0000',
            'weight_kg' => $basis === 'weight' ? '0' : '1',
        ]);
    }

    return [$file, $packages];
}

it('allocates rounding remainder to the last eligible package and preserves every cent', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();
        try {
            [$file, $packages] = v4RoundingImport();
            $item = app(SaveImportCostItem::class)->handle($file, [
                'name' => 'V4 freight rounding',
                'amount' => '0.0100',
                'currency' => 'TRY',
                'allocation_basis' => 'value',
            ]);

            app(RecalculateImportCosts::class)->handle($file, 'v4-'.Str::random(18));

            $rows = DB::connection('period')->table('import_cost_allocations')
                ->where('import_cost_item_id', $item->id)
                ->orderBy('package_id')->pluck('allocated_amount_try')->map('strval')->all();

            expect($rows)->toBe(['0.0033', '0.0033', '0.0034'])
                ->and(array_reduce($rows, fn (string $sum, string $row): string => bcadd($sum, $row, 4), '0.0000'))
                ->toBe('0.0100');

            expect((string) $packages[0]->fresh()->landed_unit_cost_try)->toBe('10.0033')
                ->and((string) $packages[2]->fresh()->landed_unit_cost_try)->toBe('10.0034');

            $again = app(RecalculateImportCosts::class)->handle($file, 'v4-'.Str::random(18));
            expect($again->packages)->toHaveCount(3)
                ->and(DB::connection('period')->table('import_cost_allocations')
                    ->where('import_cost_item_id', $item->id)->count())->toBe(3);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rolls back allocation when a positive weight-based charge has zero eligible basis', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();
        try {
            [$file] = v4RoundingImport(2, 'weight');
            $cost = app(SaveImportCostItem::class)->handle($file, [
                'name' => 'Zero basis freight',
                'amount' => '25.0000',
                'currency' => 'TRY',
                'allocation_basis' => 'weight',
            ]);

            expect(fn () => app(RecalculateImportCosts::class)->handle($file, 'v4-'.Str::random(18)))
                ->toThrow(DomainException::class);

            expect(DB::connection('period')->table('import_cost_allocations')
                ->where('import_cost_item_id', $cost->id)->count())->toBe(0)
                ->and($cost->fresh()->allocated_at)->toBeNull();
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
