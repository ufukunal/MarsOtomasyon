<?php

namespace App\Actions\Stock;

use App\Models\Period\QuarantineEntry;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReleaseQuarantine
{
    public function __construct(private readonly AdjustQuarantineBalance $adjustQuarantine) {}

    public function handle(
        int $entryId,
        string $quantity,
        string $idempotencyKey,
        ?string $note = null,
    ): QuarantineEntry {
        MutationAuthorizer::authorize('quarantine.update');
        PeriodContext::ensureWritable();

        if (bccomp($quantity, '0', 3) <= 0) {
            throw new DomainException('Serbest bırakılacak miktar pozitif olmalıdır.');
        }

        $result = IdempotencyKey::run(
            $idempotencyKey,
            "quarantine.release:{$entryId}",
            fn (): int => $this->release($entryId, $quantity, $note),
        );

        return QuarantineEntry::query()->with(['product', 'location'])->findOrFail((int) $result);
    }

    private function release(int $entryId, string $quantity, ?string $note): int
    {
        return DB::connection('period')->transaction(function () use ($entryId, $quantity, $note): int {
            $entry = QuarantineEntry::query()->lockForUpdate()->findOrFail($entryId);
            $normalized = bcadd($quantity, '0', 3);

            if (bccomp($normalized, $entry->pendingQuantity(), 3) > 0) {
                throw new DomainException('Serbest bırakma miktarı bekleyen karantina miktarını aşamaz.');
            }

            $this->adjustQuarantine->handle(
                $entry->product_id,
                $entry->location_id,
                bcmul($normalized, '-1', 3),
            );

            $newReleased = bcadd((string) $entry->released_quantity, $normalized, 3);
            $newScrapped = (string) $entry->scrapped_quantity;
            $actor = auth()->user();

            $entry->setAttribute('released_quantity', $newReleased);
            $entry->status = $entry->statusFor($newReleased, $newScrapped);
            $entry->decision_note = $note;
            $entry->decided_by = $actor?->id;
            $entry->decided_by_name = $actor?->name;
            $entry->decided_at = now();
            $entry->save();

            AuditContext::period(
                'Karantina stoğu satılabilir olarak serbest bırakıldı.',
                [
                    'quarantine_entry_id' => $entry->id,
                    'released_quantity' => $normalized,
                    'pending_quantity' => $entry->pendingQuantity(),
                ],
                $entry,
                'quarantine_released',
            );

            return $entry->id;
        }, attempts: 3);
    }
}
