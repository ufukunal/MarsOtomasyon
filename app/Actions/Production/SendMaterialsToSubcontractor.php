<?php

namespace App\Actions\Production;

use App\Actions\Stock\ReceiveTransfer;
use App\Actions\Stock\SaveTransferDraft;
use App\Actions\Stock\SendTransfer;
use App\Enums\LocationKind;
use App\Models\Period\Location;
use App\Models\Period\ProductionOrder;
use App\Models\Period\Transfer;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SendMaterialsToSubcontractor
{
    public function __construct(
        private readonly SaveTransferDraft $saveTransfer,
        private readonly SendTransfer $sendTransfer,
        private readonly ReceiveTransfer $receiveTransfer,
    ) {}

    /** @param array<int,string> $quantities */
    public function handle(
        ProductionOrder $order,
        int $fromLocationId,
        array $quantities,
        string $transferDate,
        string $idempotencyKey,
    ): Transfer {
        MutationAuthorizer::authorize('subcontracting.update');

        $transferId = IdempotencyKey::run(
            $idempotencyKey,
            'subcontract.send-materials:'.$order->id,
            fn (): int => DB::connection('period')->transaction(function () use (
                $order,
                $fromLocationId,
                $quantities,
                $transferDate,
                $idempotencyKey,
            ): int {
                $locked = ProductionOrder::query()
                    ->with('components')
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                if ($locked->production_type !== 'subcontract'
                    || ! in_array($locked->status, ['confirmed', 'in_progress'], true)) {
                    throw new DomainException('Fasona malzeme yalnız açık fason üretim emrinden gönderilebilir.');
                }

                $source = Location::query()->where('is_active', true)->findOrFail($fromLocationId);

                if ($source->kind === LocationKind::Subcontractor) {
                    throw new DomainException('Fason gönderim kaynağı normal şirket lokasyonu olmalıdır.');
                }

                $componentIds = $locked->components
                    ->pluck('component_product_id')
                    ->map(fn ($value): int => (int) $value)
                    ->all();
                $lines = [];

                foreach ($quantities as $productId => $quantity) {
                    $productId = (int) $productId;
                    $normalized = bcadd((string) $quantity, '0', 3);

                    if (bccomp($normalized, '0', 3) <= 0) {
                        continue;
                    }

                    if (! in_array($productId, $componentIds, true)) {
                        throw new DomainException('Fason gönderim satırı üretim emri componenti olmalıdır.');
                    }

                    $lines[] = ['product_id' => $productId, 'quantity' => $normalized];
                }

                if ($lines === []) {
                    throw new DomainException('Fasona gönderilecek malzeme miktarı girilmedi.');
                }

                $draft = $this->saveTransfer->handle([
                    'from_location_id' => $fromLocationId,
                    'to_location_id' => (int) $locked->subcontractor_location_id,
                    'production_order_id' => (int) $locked->id,
                    'transfer_date' => $transferDate,
                    'note' => 'Fason üretim emri '.$locked->number,
                    'lines' => $lines,
                ]);

                $sent = $this->sendTransfer->handle(
                    (int) $draft->id,
                    hash('sha256', $idempotencyKey.':send'),
                );
                $receive = [];

                foreach ($sent->lines as $line) {
                    $receive[(int) $line->id] = (string) $line->quantity;
                }

                $received = $this->receiveTransfer->handle(
                    (int) $sent->id,
                    $receive,
                    hash('sha256', $idempotencyKey.':receive'),
                );

                return (int) $received->id;
            }, attempts: 3),
        );

        return Transfer::query()
            ->with(['fromLocation', 'toLocation', 'lines.product'])
            ->findOrFail((int) $transferId);
    }
}
