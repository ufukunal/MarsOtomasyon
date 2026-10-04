<?php

namespace App\Actions\Stock;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\DataObjects\StockMovementData;
use App\Models\Period\StockMovement;
use App\Models\Period\WarehouseSlip;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReverseWarehouseSlip
{
    public function __construct(
        private readonly GenerateDocumentNumber $generateNumber,
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly RecordStockMovement $recordMovement,
    ) {}

    public function handle(int $slipId, string $idempotencyKey): WarehouseSlip
    {
        MutationAuthorizer::authorize('warehouse_slips.cancel');

        $result = IdempotencyKey::run(
            $idempotencyKey,
            "warehouse_slip.reverse:{$slipId}",
            fn (): int => $this->reverse($slipId),
        );

        return WarehouseSlip::query()->with(['location', 'lines.product'])->findOrFail((int) $result);
    }

    private function reverse(int $slipId): int
    {
        return DB::connection('period')->transaction(function () use ($slipId): int {
            $original = WarehouseSlip::query()->lockForUpdate()->findOrFail($slipId);

            if ($original->status !== 'posted') {
                throw new DomainException('Yalnız kesinleşmiş ambar fişi ters kayıtla iptal edilebilir.');
            }

            $date = CarbonImmutable::parse((string) $original->slip_date);
            $this->ensurePeriodOpen->handle($date);

            $movements = StockMovement::query()
                ->where('document_type', 'warehouse_slip')
                ->where('document_id', $original->id)
                ->orderBy('id')
                ->get();

            if ($movements->isEmpty()) {
                throw new DomainException('Ters kayıt için kaynak stok hareketi bulunamadı.');
            }

            $number = $this->generateNumber->handle('warehouse_slip', $date->year);
            $actor = auth()->user();
            $reverseDirection = $original->direction === 'in' ? 'out' : 'in';

            $reverseSlip = WarehouseSlip::query()->create([
                'number' => $number,
                'location_id' => $original->location_id,
                'slip_date' => $date->toDateString(),
                'direction' => $reverseDirection,
                'reason' => 'adjustment',
                'status' => 'posted',
                'note' => sprintf('%s no.lu fişin ters kaydı.', (string) $original->number),
                'created_by' => $actor?->id,
                'posted_by' => $actor?->id,
                'posted_at' => now(),
            ]);

            foreach ($movements as $movement) {
                $reverseSlip->lines()->create([
                    'product_id' => $movement->product_id,
                    'quantity' => (string) $movement->quantity,
                    'unit_cost' => $reverseDirection === 'in'
                        ? (string) $movement->unit_cost
                        : null,
                    'note' => 'Ters kayıt',
                ]);

                $this->recordMovement->handle(new StockMovementData(
                    productId: $movement->product_id,
                    locationId: $original->location_id,
                    movementDate: $date->toDateString(),
                    direction: $reverseDirection,
                    reason: 'adjustment',
                    quantity: (string) $movement->quantity,
                    unitCost: (string) $movement->unit_cost,
                    updatesAverage: false,
                    documentType: 'warehouse_slip',
                    documentId: $reverseSlip->id,
                    documentNo: $number,
                    note: 'Ambar fişi ters kaydı',
                    actorUserId: $actor?->id,
                    actorUserName: $actor?->name,
                ));
            }

            $original->status = 'cancelled';
            $original->save();

            AuditContext::period(
                'Ambar fişi ters kayıtla iptal edildi.',
                [
                    'warehouse_slip_id' => $original->id,
                    'number' => $original->number,
                    'reverse_slip_id' => $reverseSlip->id,
                    'reverse_number' => $number,
                ],
                $original,
                'warehouse_slip_reversed',
            );

            return $original->id;
        }, attempts: 3);
    }
}
