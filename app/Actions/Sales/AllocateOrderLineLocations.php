<?php

namespace App\Actions\Sales;

use App\Actions\Documents\SourceLineAvailability;
use App\Enums\DocumentType;
use App\Models\Period\DocumentLine;
use App\Models\Period\Location;
use App\Models\Period\StockReservation;
use DomainException;

final class AllocateOrderLineLocations
{
    public function __construct(private readonly SourceLineAvailability $availability) {}

    /**
     * @return list<array{quantity:string,location_id:int}>
     */
    public function handle(
        DocumentLine $orderLine,
        string $quantity,
        ?int $fallbackLocationId = null,
    ): array {
        $orderLine->loadMissing('document');

        if ($orderLine->document->document_type !== DocumentType::SalesOrder) {
            throw new DomainException('Lokasyon dağıtımı yalnız satış siparişi satırı için yapılabilir.');
        }

        if ($orderLine->line_kind !== 'stock') {
            return [[
                'quantity' => bcadd($quantity, '0', 3),
                'location_id' => 0,
            ]];
        }

        $requested = bcadd($quantity, '0', 3);
        $remaining = $this->availability->orderRemaining($orderLine);

        if (bccomp($requested, '0', 3) <= 0 || bccomp($requested, $remaining, 3) > 0) {
            throw new DomainException('İstenen miktar sipariş satırı kalanını aşıyor.');
        }

        $factor = (string) $orderLine->conversion_factor;
        $neededBase = bcadd(bcmul($requested, $factor, 6), '0', 3);
        $remainingBase = $neededBase;
        $parts = [];

        $reservations = StockReservation::query()
            ->where('document_line_id', $orderLine->id)
            ->where('product_id', $orderLine->product_id)
            ->where('status', 'active')
            ->orderBy('location_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            if (bccomp($remainingBase, '0', 3) <= 0) {
                break;
            }

            $takeBase = bccomp((string) $reservation->quantity, $remainingBase, 3) <= 0
                ? (string) $reservation->quantity
                : $remainingBase;
            $takeQuantity = bcdiv($takeBase, $factor, 3);

            if (bccomp($takeQuantity, '0', 3) > 0) {
                $parts[] = [
                    'quantity' => $takeQuantity,
                    'location_id' => (int) $reservation->location_id,
                ];
            }

            $remainingBase = bcsub($remainingBase, $takeBase, 3);
        }

        if (bccomp($remainingBase, '0', 3) > 0) {
            if ($fallbackLocationId === null) {
                throw new DomainException('Rezerve olmayan doğrudan miktar için lokasyon seçilmelidir.');
            }

            Location::query()->where('is_active', true)->findOrFail($fallbackLocationId);

            $parts[] = [
                'quantity' => bcdiv($remainingBase, $factor, 3),
                'location_id' => $fallbackLocationId,
            ];
        }

        return $parts;
    }
}
