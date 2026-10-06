<?php

namespace App\Actions\Stock;

use App\Enums\LocationKind;
use App\Enums\ProductKind;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\ProductionOrder;
use App\Models\Period\Transfer;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveTransferDraft
{
    /**
     * @param array{
     *   from_location_id:int,
     *   to_location_id:int,
     *   transfer_date:string,
     *   production_order_id?:int|null,
     *   note?:?string,
     *   lines:list<array{product_id:int,quantity:string}>
     * } $data
     */
    public function handle(array $data, ?Transfer $transfer = null): Transfer
    {
        MutationAuthorizer::authorize($transfer ? 'transfers.update' : 'transfers.create');
        PeriodContext::ensureWritable();

        if ($data['from_location_id'] === $data['to_location_id']) {
            throw ValidationException::withMessages([
                'to_location_id' => 'Kaynak ve hedef lokasyon aynı olamaz.',
            ]);
        }

        $fromLocation = Location::query()->findOrFail($data['from_location_id']);
        $toLocation = Location::query()->findOrFail($data['to_location_id']);
        $productionOrderId = isset($data['production_order_id']) && $data['production_order_id']
            ? (int) $data['production_order_id']
            : null;

        if ($fromLocation->kind === LocationKind::Subcontractor
            || $toLocation->kind === LocationKind::Subcontractor) {
            if ($productionOrderId === null) {
                throw new DomainException('Fason lokasyon transferi üretim emri provenance gerektirir.');
            }

            $productionOrder = ProductionOrder::query()->findOrFail($productionOrderId);

            if ($productionOrder->production_type !== 'subcontract'
                || (int) $productionOrder->subcontractor_location_id !== (int) $toLocation->id
                || $fromLocation->kind === LocationKind::Subcontractor) {
                throw new DomainException('Fason transfer lokasyonları üretim emriyle eşleşmiyor.');
            }
        } elseif ($productionOrderId !== null) {
            throw new DomainException('Production-order transfer provenance yalnız fason lokasyon sevkinde kullanılabilir.');
        }

        $transferDate = Carbon::parse($data['transfer_date']);

        if ($data['lines'] === []) {
            throw ValidationException::withMessages([
                'lines' => 'Transfer en az bir ürün satırı içermelidir.',
            ]);
        }

        foreach ($data['lines'] as $index => $line) {
            if (bccomp($line['quantity'], '0', 3) <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity" => 'Transfer miktarı pozitif olmalıdır.',
                ]);
            }

            $product = Product::query()->findOrFail($line['product_id']);

            if ($product->kind === ProductKind::Set) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => 'Set ürün fiziksel transfer satırı olamaz.',
                ]);
            }
        }

        return DB::connection('period')->transaction(function () use ($data, $transfer, $productionOrderId, $transferDate): Transfer {
            if ($transfer) {
                $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);

                if ($transfer->status !== 'draft') {
                    throw new DomainException('Yalnız taslak transfer düzenlenebilir.');
                }
            } else {
                $transfer = new Transfer;
                $transfer->status = 'draft';
                $transfer->created_by = auth()->id();
            }

            $transfer->from_location_id = $data['from_location_id'];
            $transfer->to_location_id = $data['to_location_id'];
            $transfer->production_order_id = $productionOrderId;
            $transfer->transfer_date = $transferDate;
            $transfer->note = $data['note'] ?? null;
            $transfer->save();

            $transfer->lines()->delete();

            foreach ($data['lines'] as $line) {
                $transfer->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => bcadd($line['quantity'], '0', 3),
                    'received_quantity' => '0.000',
                ]);
            }

            return $transfer->refresh()->load(['fromLocation', 'toLocation', 'lines.product']);
        }, attempts: 3);
    }
}
