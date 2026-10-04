<?php

namespace App\Actions\Stock;

use App\DataObjects\StockMovementData;
use App\Models\Period\StockMovement;
use App\Models\Period\Transfer;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CancelTransfer
{
    public function __construct(private readonly RecordStockMovement $recordMovement) {}

    public function handle(int $transferId, string $idempotencyKey): Transfer
    {
        MutationAuthorizer::authorize('transfers.cancel');
        PeriodContext::ensureWritable();

        $result = IdempotencyKey::run(
            $idempotencyKey,
            "transfer.cancel:{$transferId}",
            fn (): int => $this->cancel($transferId),
        );

        return Transfer::query()->with(['fromLocation', 'toLocation', 'lines.product'])->findOrFail((int) $result);
    }

    private function cancel(int $transferId): int
    {
        return DB::connection('period')->transaction(function () use ($transferId): int {
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transferId);

            if (! in_array($transfer->status, ['draft', 'in_transit', 'partially_received'], true)) {
                throw new DomainException('Teslim alınmış veya iptal edilmiş transfer iptal edilemez.');
            }

            $previousStatus = $transfer->status;
            $date = CarbonImmutable::parse((string) $transfer->transfer_date);
            $actor = auth()->user();

            if ($previousStatus !== 'draft') {
                $lines = $transfer->lines()
                    ->with('product')
                    ->orderBy('product_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lines as $line) {
                    $remaining = $line->remainingQuantity();

                    if (bccomp($remaining, '0', 3) <= 0) {
                        continue;
                    }

                    $sourceMovement = StockMovement::query()
                        ->where('document_type', 'transfer')
                        ->where('document_id', $transfer->id)
                        ->where('product_id', $line->product_id)
                        ->where('location_id', $transfer->from_location_id)
                        ->where('direction', 'out')
                        ->where('reason', 'transfer')
                        ->orderBy('id')
                        ->first();

                    if (! $sourceMovement) {
                        throw new DomainException('Transfer iptal maliyet hareketi bulunamadı.');
                    }

                    $this->recordMovement->handle(new StockMovementData(
                        productId: $line->product_id,
                        locationId: $transfer->from_location_id,
                        movementDate: $date->toDateString(),
                        direction: 'in',
                        reason: 'transfer',
                        quantity: $remaining,
                        unitCost: (string) $sourceMovement->unit_cost,
                        updatesAverage: false,
                        documentType: 'transfer',
                        documentId: $transfer->id,
                        documentNo: $transfer->number,
                        note: 'Transfer iptal ters kaydı',
                        actorUserId: $actor?->id,
                        actorUserName: $actor?->name,
                    ));
                }
            }

            $transfer->status = 'cancelled';
            $transfer->save();

            AuditContext::period(
                'Transfer iptal edildi.',
                [
                    'transfer_id' => $transfer->id,
                    'number' => $transfer->number,
                    'previous_status' => $previousStatus,
                ],
                $transfer,
                'transfer_cancelled',
            );

            return $transfer->id;
        }, attempts: 3);
    }
}
