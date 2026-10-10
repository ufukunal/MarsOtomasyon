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

it('invalidates all previous landed-cost allocations when the import currency rate changes', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();
        try {
            $ids = IsolatedPostgres::productAndLocation();
            $contact = Contact::query()->create(['title' => 'V4 FX supplier', 'type' => 'legal']);
            $save = app(SaveImportFile::class);
            $file = $save->handle([
                'supplier_contact_id' => $contact->id,
                'currency' => 'USD',
                'exchange_rate' => '30.000000',
            ]);
            $container = app(SaveImportContainer::class)->handle($file, [
                'container_no' => 'V4-'.Str::random(10),
            ]);
            $package = app(SaveImportPackage::class)->handle($file, [
                'container_id' => $container->id,
                'carton_no' => 'V4-FX-1',
                'product_id' => $ids['product'],
                'location_id' => $ids['location'],
                'quantity' => '2',
                'unit_price' => '10',
            ]);
            $cost = app(SaveImportCostItem::class)->handle($file, [
                'name' => 'Handling',
                'amount' => '5.0000',
                'currency' => 'USD',
                'allocation_basis' => 'value',
            ]);

            app(RecalculateImportCosts::class)->handle($file, 'v4-'.Str::random(18));

            expect($package->fresh()->landed_unit_cost_try)->not->toBeNull()
                ->and(DB::connection('period')->table('import_cost_allocations')
                    ->where('import_cost_item_id', $cost->id)->count())->toBe(1);

            $updated = $save->handle([
                'supplier_contact_id' => $contact->id,
                'currency' => 'USD',
                'exchange_rate' => '35.000000',
            ], $file, (int) $file->version);

            expect((string) $updated->exchange_rate)->toBe('35.000000')
                ->and($cost->fresh()->amount_try)->toBeNull()
                ->and($package->fresh()->landed_unit_cost_try)->toBeNull()
                ->and(DB::connection('period')->table('import_cost_allocations')
                    ->where('import_cost_item_id', $cost->id)->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
