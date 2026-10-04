<?php

namespace App\Actions\Sales;

use App\Actions\Documents\SourceLineAvailability;
use App\Actions\Stock\ReleaseReservation;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\StockReservation;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class CancelSalesOrderRemaining
{
    public function __construct(
        private readonly SourceLineAvailability $availability,
        private readonly ReleaseReservation $releaseReservation,
    ) {}

    /** @param list<int>|null $lineIds */
    public function handle(
        Document $order,
        string $idempotencyKey,
        ?array $lineIds = null,
    ): Document {
        MutationAuthorizer::authorize('sales_orders.cancel');

        return IdempotencyKey::run(
            $idempotencyKey,
            'sales-order.cancel-remaining:'.$order->id,
            function () use ($order, $idempotencyKey, $lineIds): Document {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($order->id);

                if ($locked->document_type !== DocumentType::SalesOrder || $locked->status !== 'confirmed') {
                    throw new DomainException('Kalan iptali yalnız onaylı satış siparişinde yapılabilir.');
                }

                $targetIds = $lineIds === null
                    ? $locked->lines->pluck('id')->map(fn ($id): int => (int) $id)->all()
                    : array_values(array_map('intval', $lineIds));

                foreach ($targetIds as $lineId) {
                    $line = DocumentLine::query()
                        ->where('document_id', $locked->id)
                        ->lockForUpdate()
                        ->findOrFail($lineId);
                    $remaining = $this->availability->orderRemaining($line);

                    if (bccomp($remaining, '0', 3) <= 0) {
                        continue;
                    }

                    $reservations = StockReservation::query()
                        ->where('document_line_id', $line->id)
                        ->where('status', 'active')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($reservations as $reservation) {
                        $this->releaseReservation->handle(
                            (int) $reservation->id,
                            hash('sha256', $idempotencyKey.':release:'.$reservation->id),
                        );
                    }

                    $line->cancelled_quantity = bcadd(
                        (string) $line->cancelled_quantity,
                        $remaining,
                        3,
                    );
                    $line->version = (int) $line->version + 1;
                    $line->save();
                }

                $allClosed = $locked->lines()
                    ->get()
                    ->every(fn (DocumentLine $line): bool =>
                        bccomp($this->availability->orderRemaining($line), '0', 3) <= 0
                    );

                if ($allClosed) {
                    $locked->status = 'closed';
                    $locked->version = (int) $locked->version + 1;
                    $locked->save();
                }

                AuditContext::period(
                    'Satış siparişi kalan miktarı iptal edildi.',
                    ['order_id' => $locked->id, 'line_ids' => $targetIds],
                    $locked,
                    'sales_order_remaining_cancelled',
                );

                return $locked->refresh();
            },
        );
    }
}
