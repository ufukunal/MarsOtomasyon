<?php

use App\Actions\Returns\ResolveSalesReturnUnitCost;
use App\Models\Period\DocumentLine;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('uses the recorded moving average only when no linked invoice or dispatch stock issue exists', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $fixture = IsolatedPostgres::productAndLocation();
        $db = DB::connection('period');
        $db->table('product_costs')->insert([
            'product_id' => $fixture['product'],
            'moving_average' => '8.2500',
            'last_purchase_price' => '10.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $invoiceId = $db->table('documents')->insertGetId([
            'document_type' => 'sales_invoice',
            'document_date' => '2026-10-10',
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $lineId = $db->table('document_lines')->insertGetId([
            'document_id' => $invoiceId,
            'line_no' => 1,
            'line_kind' => 'stock',
            'product_id' => $fixture['product'],
            'unit_id' => $fixture['unit'],
            'location_id' => $fixture['location'],
            'quantity' => '1.000',
            'base_quantity' => '1.000',
            'conversion_factor' => '1.000000',
            'unit_price' => '25.0000',
            'line_total' => '25.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $invoiceLine = DocumentLine::query()->findOrFail($lineId);

        expect(app(ResolveSalesReturnUnitCost::class)->handle($invoiceLine))->toBe('8.2500');
    });
});
