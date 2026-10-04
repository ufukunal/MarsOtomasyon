<?php

namespace App\Actions\Sales;

use App\Actions\Documents\SourceLineAvailability;
use App\Actions\Stock\ReserveStock;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class ReserveSalesOrderLines
{
    public function __construct(
        private readonly SourceLineAvailability $availability,
        private readonly ReserveStock $reserveStock,
    ) {}

    /**
     * @param array<int,list<int>> $lineLocationPriorities
     * @return array<int,array{reservation_ids:list<int>,reserved_quantity:string,open_quantity:string}>
     */
    public function handle(
        Document $order,
        array $lineLocationPriorities,
        string $idempotencyKey,
    ): array {
        MutationAuthorizer::authorize('sales_orders.update');

        return IdempotencyKey::run(
            $idempotencyKey,
            'sales-order.reserve:'.$order->id,
            function () use ($order, $lineLocationPriorities, $idempotencyKey): array {
                $locked = Document::query()->lockForUpdate()->findOrFail($order->id);

                if ($locked->document_type !== DocumentType::SalesOrder || $locked->status !== 'confirmed') {
                    throw new DomainException('Rezervasyon yalnız onaylı satış siparişinde yapılabilir.');
                }

                $actor = auth()->user();
                $results = [];

                foreach ($lineLocationPriorities as $lineId => $locationIds) {
                    $line = DocumentLine::query()
                        ->where('document_id', $locked->id)
                        ->lockForUpdate()
                        ->findOrFail((int) $lineId);

                    if ($line->line_kind !== 'stock') {
                        continue;
                    }

                    $remaining = $this->availability->orderRemaining($line);

                    if (bccomp($remaining, '0', 3) <= 0) {
                        continue;
                    }

                    $requestedBase = bcadd(
                        bcmul($remaining, (string) $line->conversion_factor, 6),
                        '0',
                        3,
                    );

                    $result = $this->reserveStock->handle(
                        productId: (int) $line->product_id,
                        requestedQuantity: $requestedBase,
                        orderedLocationIds: array_values(array_map('intval', $locationIds)),
                        documentType: DocumentType::SalesOrder->value,
                        documentId: (int) $locked->id,
                        documentLineId: (int) $line->id,
                        idempotencyKey: hash('sha256', $idempotencyKey.':line:'.$line->id),
                        actorUserId: $actor?->id,
                        actorUserName: $actor?->name,
                    );

                    $results[(int) $line->id] = [
                        'reservation_ids' => $result->reservationIds,
                        'reserved_quantity' => $result->reservedQuantity,
                        'open_quantity' => $result->openQuantity,
                    ];
                }

                return $results;
            },
        );
    }
}
