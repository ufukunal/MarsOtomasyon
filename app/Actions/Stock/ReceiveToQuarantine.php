<?php

namespace App\Actions\Stock;

use App\DataObjects\QuarantineReceiptData;
use App\DataObjects\StockMovementData;
use App\Models\Period\QuarantineEntry;
use App\Support\Audit\AuditContext;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReceiveToQuarantine
{
    public function __construct(
        private readonly RecordStockMovement $recordMovement,
        private readonly AdjustQuarantineBalance $adjustQuarantine,
    ) {}

    public function handle(QuarantineReceiptData $data, string $idempotencyKey): QuarantineEntry
    {
        if (bccomp($data->quantity, '0', 3) <= 0) {
            throw new DomainException('Karantina miktarı pozitif olmalıdır.');
        }

        if (bccomp($data->unitCost, '0', 4) < 0) {
            throw new DomainException('Karantina birim maliyeti negatif olamaz.');
        }

        $result = IdempotencyKey::run(
            $idempotencyKey,
            sprintf(
                'quarantine.receive:%s:%s:%s:%d:%d',
                $data->sourceDocumentType ?? 'manual',
                $data->sourceDocumentId ?? 0,
                $data->sourceLineId ?? 0,
                $data->productId,
                $data->locationId,
            ),
            fn (): int => $this->receive($data),
        );

        return QuarantineEntry::query()->with(['product', 'location'])->findOrFail((int) $result);
    }

    private function receive(QuarantineReceiptData $data): int
    {
        return DB::connection('period')->transaction(function () use ($data): int {
            $movement = $this->recordMovement->handle(new StockMovementData(
                productId: $data->productId,
                locationId: $data->locationId,
                movementDate: $data->movementDate,
                direction: 'in',
                reason: 'return',
                quantity: $data->quantity,
                unitCost: $data->unitCost,
                updatesAverage: false,
                documentType: $data->sourceDocumentType,
                documentId: $data->sourceDocumentId,
                documentNo: $data->documentNo,
                note: $data->note,
                actorUserId: $data->actorUserId,
                actorUserName: $data->actorUserName,
            ));

            $this->adjustQuarantine->handle(
                $data->productId,
                $data->locationId,
                bcadd($data->quantity, '0', 3),
            );

            $entry = QuarantineEntry::query()->create([
                'product_id' => $data->productId,
                'location_id' => $data->locationId,
                'source_document_type' => $data->sourceDocumentType,
                'source_document_id' => $data->sourceDocumentId,
                'source_line_id' => $data->sourceLineId,
                'quantity' => bcadd($data->quantity, '0', 3),
                'released_quantity' => '0.000',
                'scrapped_quantity' => '0.000',
                'unit_cost' => (string) $movement->unit_cost,
                'status' => 'pending',
                'created_by' => $data->actorUserId,
                'created_by_name' => $data->actorUserName,
            ]);

            AuditContext::period(
                'Stok karantinaya alındı.',
                [
                    'quarantine_entry_id' => $entry->id,
                    'product_id' => $entry->product_id,
                    'location_id' => $entry->location_id,
                    'quantity' => (string) $entry->quantity,
                ],
                $entry,
                'quarantine_received',
            );

            return $entry->id;
        }, attempts: 3);
    }
}
