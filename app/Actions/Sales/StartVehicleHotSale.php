<?php

namespace App\Actions\Sales;

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Enums\DocumentType;
use App\Models\Period\Location;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class StartVehicleHotSale
{
    public function __construct(
        private readonly SaveSalesDocumentDraft $saveDraft,
        private readonly PostSalesInvoice $postInvoice,
    ) {}

    /**
     * @param array<string,mixed> $header
     * @param list<array<string,mixed>> $lines
     */
    public function handle(
        int $vehicleLocationId,
        array $header,
        array $lines,
        string $idempotencyKey,
    ) {
        MutationAuthorizer::authorize('sales_invoices.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'vehicle-hot-sale.create',
            function () use ($vehicleLocationId, $header, $lines, $idempotencyKey) {
                $vehicle = Location::query()
                    ->where('kind', 'vehicle')
                    ->where('is_active', true)
                    ->findOrFail($vehicleLocationId);

                $normalized = array_map(function (array $line) use ($vehicle): array {
                    if (($line['line_kind'] ?? 'stock') !== 'stock') {
                        throw new DomainException('Araç sıcak satışında yalnız stok satırı kullanılabilir.');
                    }

                    $line['location_id'] = $vehicle->id;
                    $line['source_line_id'] = null;

                    return $line;
                }, $lines);

                $invoice = $this->saveDraft->handle(
                    DocumentType::SalesInvoice,
                    $header,
                    $normalized,
                );

                return $this->postInvoice->handle(
                    $invoice,
                    hash('sha256', $idempotencyKey.':post'),
                );
            },
        );
    }
}
