<?php

namespace App\Actions\Production;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Enums\DocumentType;
use App\Enums\LocationKind;
use App\Enums\ProductKind;
use App\Models\Period\Document;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionRecipe;
use App\Support\Auth\MutationAuthorizer;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveProductionOrderDraft
{
    public function __construct(private readonly EnsurePeriodOpen $ensurePeriodOpen) {}

    /** @param array<string,mixed> $data */
    public function handle(
        array $data,
        ?ProductionOrder $order = null,
        ?int $expectedVersion = null,
    ): ProductionOrder {
        MutationAuthorizer::authorize('production_orders.'.($order ? 'update' : 'create'));

        $date = CarbonImmutable::parse((string) ($data['document_date'] ?? now()->toDateString()));
        $this->ensurePeriodOpen->handle($date);

        return DB::connection('period')->transaction(function () use ($data, $order, $expectedVersion, $date): ProductionOrder {
            $locked = $order
                ? ProductionOrder::query()->lockForUpdate()->findOrFail($order->id)
                : new ProductionOrder;

            if ($locked->exists && $locked->status !== 'draft') {
                throw new DomainException('Yalnız taslak üretim emri düzenlenebilir.');
            }

            $productId = (int) ($data['product_id'] ?? 0);
            $recipeId = (int) ($data['recipe_id'] ?? 0);
            $planned = bcadd((string) ($data['planned_quantity'] ?? '0'), '0', 3);

            if (bccomp($planned, '0', 3) <= 0) {
                throw new DomainException('Planlanan üretim miktarı pozitif olmalıdır.');
            }

            $product = Product::query()->findOrFail($productId);

            if ($product->kind === ProductKind::Set) {
                throw new DomainException('Set ürün fiziksel üretim emri olamaz.');
            }

            $recipe = ProductionRecipe::query()->findOrFail($recipeId);

            if ((int) $recipe->product_id !== $productId) {
                throw new DomainException('Üretim emri mamulü ile reçete mamulü eşleşmiyor.');
            }

            $type = (string) ($data['production_type'] ?? 'internal');

            if (! in_array($type, ['internal', 'subcontract'], true)) {
                throw new DomainException('Üretim tipi internal veya subcontract olmalıdır.');
            }

            $subcontractorContactId = null;
            $subcontractorLocationId = null;

            if ($type === 'subcontract') {
                $subcontractorContactId = (int) ($data['subcontractor_contact_id'] ?? 0);
                $subcontractorLocationId = (int) ($data['subcontractor_location_id'] ?? 0);
                $location = Location::query()->findOrFail($subcontractorLocationId);

                if ($location->kind !== LocationKind::Subcontractor
                    || (int) $location->subcontractor_contact_id !== $subcontractorContactId) {
                    throw new DomainException('Fason lokasyon ve fasoncu cari eşleşmiyor.');
                }
            }

            $sourceSalesOrderId = ($data['source_sales_order_id'] ?? null)
                ? (int) $data['source_sales_order_id']
                : null;

            if ($sourceSalesOrderId !== null) {
                $source = Document::query()->findOrFail($sourceSalesOrderId);

                if ($source->document_type !== DocumentType::SalesOrder || $source->status !== 'confirmed') {
                    throw new DomainException('Kaynak satış siparişi onaylı sales_order olmalıdır.');
                }
            }

            $attributes = [
                'document_date' => $date->toDateString(),
                'product_id' => $productId,
                'recipe_id' => $recipe->id,
                'recipe_revision_no' => $recipe->revision_no,
                'planned_quantity' => $planned,
                'production_type' => $type,
                'subcontractor_contact_id' => $subcontractorContactId,
                'subcontractor_location_id' => $subcontractorLocationId,
                'source_sales_order_id' => $sourceSalesOrderId,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ];

            if ($locked->exists) {
                return $locked->updateWithVersion(
                    $attributes,
                    $expectedVersion ?? (int) $locked->version,
                )->refresh();
            }

            $actor = auth()->user();

            return ProductionOrder::query()->create([
                ...$attributes,
                'completed_quantity' => '0.000',
                'cancelled_quantity' => '0.000',
                'status' => 'draft',
                'version' => 1,
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
            ])->refresh();
        }, attempts: 3);
    }
}
