<?php

namespace App\Actions\Stock;

use App\DataObjects\StockMovementData;
use App\Models\Period\QuarantineEntry;
use App\Models\Period\StockBalance;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ScrapQuarantine
{
    public function __construct(
        private readonly RecordStockMovement $recordMovement,
        private readonly AdjustQuarantineBalance $adjustQuarantine,
    ) {}

    public function handle(
        int $entryId,
        string $quantity,
        string $idempotencyKey,
        ?string $note = null,
        ?string $movementDate = null,
    ): QuarantineEntry {
        MutationAuthorizer::authorize('quarantine.update');
        PeriodContext::ensureWritable();

        if (bccomp($quantity, '0', 3) <= 0) {
            throw new DomainException('Hurdaya ayrılacak miktar pozitif olmalıdır.');
        }

        $result = IdempotencyKey::run(
            $idempotencyKey,
            "quarantine.scrap:{$entryId}",
            fn (): int => $this->scrap(
                $entryId,
                $quantity,
                $note,
                $movementDate ?? now()->toDateString(),
            ),
        );

        return QuarantineEntry::query()->with(['product', 'location'])->findOrFail((int) $result);
    }

    private function scrap(
        int $entryId,
        string $quantity,
        ?string $note,
        string $movementDate,
    ): int {
        return DB::connection('period')->transaction(function () use (
            $entryId,
            $quantity,
            $note,
            $movementDate,
        ): int {
            $entry = QuarantineEntry::query()->lockForUpdate()->findOrFail($entryId);
            $normalized = bcadd($quantity, '0', 3);

            if (bccomp($normalized, $entry->pendingQuantity(), 3) > 0) {
                throw new DomainException('Hurda miktarı bekleyen karantina miktarını aşamaz.');
            }

            $balance = StockBalance::query()
                ->where('product_id', $entry->product_id)
                ->where('location_id', $entry->location_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (bccomp((string) $balance->quantity, $normalized, 3) < 0) {
                throw new DomainException('Fiziksel stok hurda miktarını karşılamıyor.');
            }

            $this->adjustQuarantine->handle(
                $entry->product_id,
                $entry->location_id,
                bcmul($normalized, '-1', 3),
            );

            $actor = auth()->user();

            $this->recordMovement->handle(new StockMovementData(
                productId: $entry->product_id,
                locationId: $entry->location_id,
                movementDate: $movementDate,
                direction: 'out',
                reason: 'scrap',
                quantity: $normalized,
                unitCost: (string) $entry->unit_cost,
                updatesAverage: false,
                documentType: 'quarantine',
                documentId: $entry->id,
                note: $note,
                actorUserId: $actor?->id,
                actorUserName: $actor?->name,
            ));

            $newReleased = (string) $entry->released_quantity;
            $newScrapped = bcadd((string) $entry->scrapped_quantity, $normalized, 3);

            $entry->setAttribute('scrapped_quantity', $newScrapped);
            $entry->status = $entry->statusFor($newReleased, $newScrapped);
            $entry->decision_note = $note;
            $entry->decided_by = $actor?->id;
            $entry->decided_by_name = $actor?->name;
            $entry->decided_at = now();
            $entry->save();

            AuditContext::period(
                'Karantina stoğu hurdaya ayrıldı.',
                [
                    'quarantine_entry_id' => $entry->id,
                    'scrapped_quantity' => $normalized,
                    'pending_quantity' => $entry->pendingQuantity(),
                ],
                $entry,
                'quarantine_scrapped',
            );

            return $entry->id;
        }, attempts: 3);
    }
}
