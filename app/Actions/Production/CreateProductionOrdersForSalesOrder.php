<?php

namespace App\Actions\Production;

use App\Enums\ChannelStockMode;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\Product;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionRecipe;
use DomainException;

final class CreateProductionOrdersForSalesOrder
{
    /** @return list<int> */
    public function handle(Document $salesOrder): array
    {
        $salesOrder->loadMissing('lines');

        if ($salesOrder->document_type !== DocumentType::SalesOrder || $salesOrder->status !== 'confirmed') {
            throw new DomainException('Production-mode otomasyonu yalnız onaylı satış siparişinde çalışır.');
        }

        $requirements = [];

        foreach ($salesOrder->lines as $line) {
            if ($line->line_kind !== 'stock' || $line->product_id === null || $line->base_quantity === null) {
                continue;
            }

            $product = Product::query()->findOrFail((int) $line->product_id);

            if ($product->channel_stock_mode !== ChannelStockMode::Production) {
                continue;
            }

            $productId = (int) $product->id;
            $requirements[$productId] = bcadd(
                $requirements[$productId] ?? '0.000',
                (string) $line->base_quantity,
                3,
            );
        }

        ksort($requirements, SORT_NUMERIC);
        $ids = [];
        $actor = auth()->user();

        foreach ($requirements as $productId => $quantity) {
            if (bccomp($quantity, '0', 3) <= 0) {
                continue;
            }

            $existing = ProductionOrder::query()
                ->where('source_sales_order_id', $salesOrder->id)
                ->where('product_id', $productId)
                ->whereNotIn('status', ['cancelled'])
                ->first();

            if ($existing) {
                $ids[] = (int) $existing->id;

                continue;
            }

            $recipe = ProductionRecipe::query()
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $recipe) {
                throw new DomainException('Production-mode satış ürünü için aktif üretim reçetesi bulunamadı.');
            }

            $created = ProductionOrder::query()->create([
                'document_date' => $salesOrder->document_date->toDateString(),
                'product_id' => $productId,
                'recipe_id' => $recipe->id,
                'recipe_revision_no' => $recipe->revision_no,
                'planned_quantity' => $quantity,
                'completed_quantity' => '0.000',
                'cancelled_quantity' => '0.000',
                'production_type' => 'internal',
                'source_sales_order_id' => $salesOrder->id,
                'status' => 'draft',
                'version' => 1,
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
            ]);

            $ids[] = (int) $created->id;
        }

        return $ids;
    }
}
