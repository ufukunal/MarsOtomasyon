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

final class ReceiveTransfer
{
    public function __construct(private readonly RecordStockMovement $recordMovement) {}

    /** @param array<int, string> $quantities */
    public function handle(
        int $transferId,
        array $quantities,
        string $idempotencyKey,
    ): Transfer {
        MutationAuthorizer::authorize('transfers.update');
        PeriodContext::ensureWritable();

        $result = IdempotencyKey::run(
            $idempotencyKey,
            "transfer.receive:{$transferId}",
            fn (): int => $this->receive($transferId, $quantities),
        );

        return Transfer::query()->with(['fromLocation', 'toLocation', 'lines.product'])->findOrFail((int) $result);
    }

    /** @param array<int, string> $quantities */
    private function receive(int $transferId, array $quantities): int
    {
        return DB::connection('period')->transaction(function () use ($transferId, $quantities): int {
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transferId);

            if (! in_array($transfer->status, ['in_transit', 'partially_received'], true)) {
                throw new DomainException('Yalnız yoldaki transfer teslim alınabilir.');
            }

            $lines = $transfer->lines()
                ->with('product')
                ->orderBy('product_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $date = CarbonImmutable::parse((string) $transfer->transfer_date);
            $actor = auth()->user();
            $receivedAny = false;

            foreach ($lines as $line) {
                $requested = bcadd((string) ($quantities[$line->id] ?? '0'), '0', 3);

                if (bccomp($requested, '0', 3) < 0) {
                    throw new DomainException('Teslim alınan miktar negatif olamaz.');
                }

                if (bccomp($requested, '0', 3) === 0) {
                    continue;
                }

                $remaining = $line->remainingQuantity();

                if (bccomp($requested, $remaining, 3) > 0) {
                    throw new DomainException(sprintf(
                        '%s için teslim miktarı kalan transit miktarı aşamaz.',
                        $line->product->code,
                    ));
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
                    throw new DomainException('Transfer kaynak maliyet hareketi bulunamadı.');
                }

                $this->recordMovement->handle(new StockMovementData(
                    productId: $line->product_id,
                    locationId: $transfer->to_location_id,
                    movementDate: $date->toDateString(),
                    direction: 'in',
                    reason: 'transfer',
                    quantity: $requested,
                    unitCost: (string) $sourceMovement->unit_cost,
                    updatesAverage: false,
                    documentType: 'transfer',
                    documentId: $transfer->id,
                    documentNo: $transfer->number,
                    actorUserId: $actor?->id,
                    actorUserName: $actor?->name,
                ));

                $line->setAttribute(
                    'received_quantity',
                    bcadd((string) $line->received_quantity, $requested, 3),
                );
                $line->save();
                $receivedAny = true;
            }

            if (! $receivedAny) {
                throw new DomainException('Teslim alınacak miktar girilmedi.');
            }

            $allReceived = $transfer->lines()
                ->orderBy('id')
                ->get()
                ->every(fn ($line): bool => bccomp(
                    (string) $line->received_quantity,
                    (string) $line->quantity,
                    3,
                ) === 0);

            $transfer->status = $allReceived ? 'received' : 'partially_received';
            $transfer->save();

            AuditContext::period(
                $allReceived ? 'Transfer tamamen teslim alındı.' : 'Transfer kısmen teslim alındı.',
                [
                    'transfer_id' => $transfer->id,
                    'number' => $transfer->number,
                    'status' => $transfer->status,
                ],
                $transfer,
                'transfer_received',
            );

            return $transfer->id;
        }, attempts: 3);
    }
}
