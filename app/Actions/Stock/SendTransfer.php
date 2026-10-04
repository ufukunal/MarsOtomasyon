<?php

namespace App\Actions\Stock;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\DataObjects\StockMovementData;
use App\Models\Period\StockBalance;
use App\Models\Period\Transfer;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SendTransfer
{
    public function __construct(
        private readonly GenerateDocumentNumber $generateNumber,
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly RecordStockMovement $recordMovement,
    ) {}

    public function handle(int $transferId, string $idempotencyKey): Transfer
    {
        MutationAuthorizer::authorize('transfers.update');
        PeriodContext::ensureWritable();

        $result = IdempotencyKey::run(
            $idempotencyKey,
            "transfer.send:{$transferId}",
            fn (): int => $this->send($transferId),
        );

        return Transfer::query()->with(['fromLocation', 'toLocation', 'lines.product'])->findOrFail((int) $result);
    }

    private function send(int $transferId): int
    {
        return DB::connection('period')->transaction(function () use ($transferId): int {
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transferId);

            if ($transfer->status !== 'draft') {
                throw new DomainException('Yalnız taslak transfer gönderilebilir.');
            }

            if ($transfer->from_location_id === $transfer->to_location_id) {
                throw new DomainException('Kaynak ve hedef lokasyon aynı olamaz.');
            }

            $date = CarbonImmutable::parse((string) $transfer->transfer_date);
            $this->ensurePeriodOpen->handle($date);

            $lines = $transfer->lines()
                ->with('product')
                ->orderBy('product_id')
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw new DomainException('Transfer satırı bulunamadı.');
            }

            $number = $transfer->number ?: $this->generateNumber->handle('transfer', $date->year);
            $actor = auth()->user();

            foreach ($lines as $line) {
                $balance = StockBalance::query()
                    ->where('product_id', $line->product_id)
                    ->where('location_id', $transfer->from_location_id)
                    ->lockForUpdate()
                    ->first();

                $available = $balance?->available() ?? '0.000';

                if (bccomp($available, (string) $line->quantity, 3) < 0) {
                    throw new DomainException(sprintf(
                        '%s için transfer edilebilir stok yetersiz.',
                        $line->product->code,
                    ));
                }

                $this->recordMovement->handle(new StockMovementData(
                    productId: $line->product_id,
                    locationId: $transfer->from_location_id,
                    movementDate: $date->toDateString(),
                    direction: 'out',
                    reason: 'transfer',
                    quantity: (string) $line->quantity,
                    documentType: 'transfer',
                    documentId: $transfer->id,
                    documentNo: $number,
                    actorUserId: $actor?->id,
                    actorUserName: $actor?->name,
                ));
            }

            $transfer->number = $number;
            $transfer->status = 'in_transit';
            $transfer->posted_by = $actor?->id;
            $transfer->posted_at = now();
            $transfer->save();

            AuditContext::period(
                'Transfer gönderildi.',
                [
                    'transfer_id' => $transfer->id,
                    'number' => $number,
                    'from_location_id' => $transfer->from_location_id,
                    'to_location_id' => $transfer->to_location_id,
                ],
                $transfer,
                'transfer_sent',
            );

            return $transfer->id;
        }, attempts: 3);
    }
}
