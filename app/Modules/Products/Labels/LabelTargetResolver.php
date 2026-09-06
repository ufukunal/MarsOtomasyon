<?php

namespace App\Modules\Products\Labels;

use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Product;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

final class LabelTargetResolver
{
    /**
     * @return array{payload: array<string, mixed>, barcode_id: int|null}
     */
    public function resolve(int $companyId, string $targetType, int $targetId, ?int $barcodeId = null): array
    {
        return match ($targetType) {
            'product' => $this->product($companyId, $targetId, $barcodeId),
            'warehouse' => $this->warehouse($companyId, $targetId),
            'location' => $this->location($companyId, $targetId),
            'shipment' => $this->shipment($companyId, $targetId),
            'package' => throw ValidationException::withMessages([
                'target_type' => 'Package label authority is not available.',
            ]),
            default => throw ValidationException::withMessages([
                'target_type' => 'Unsupported label target type.',
            ]),
        };
    }

    /** @return array{payload: array<string, mixed>, barcode_id: int} */
    private function product(int $companyId, int $productId, ?int $barcodeId): array
    {
        $product = Product::query()
            ->whereKey($productId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $barcodeQuery = Barcode::query()
            ->where('company_id', $companyId)
            ->where('product_id', $product->getKey());

        $barcode = $barcodeId !== null
            ? (clone $barcodeQuery)->whereKey($barcodeId)->firstOrFail()
            : (clone $barcodeQuery)->orderByDesc('is_primary')->orderBy('id')->first();

        if ($barcode === null) {
            throw ValidationException::withMessages([
                'barcode_id' => 'A canonical product barcode is required for product labels.',
            ]);
        }

        return [
            'payload' => [
                'product' => [
                    'id' => $product->getKey(),
                    'sku' => $product->code,
                    'name' => $product->name,
                ],
                'barcode' => $barcode->barcode,
            ],
            'barcode_id' => (int) $barcode->getKey(),
        ];
    }

    /** @return array{payload: array<string, mixed>, barcode_id: null} */
    private function warehouse(int $companyId, int $warehouseId): array
    {
        $warehouse = Warehouse::query()->whereKey($warehouseId)->where('company_id', $companyId)->firstOrFail();

        return [
            'payload' => [
                'warehouse' => [
                    'id' => $warehouse->getKey(),
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                ],
            ],
            'barcode_id' => null,
        ];
    }

    /** @return array{payload: array<string, mixed>, barcode_id: null} */
    private function location(int $companyId, int $locationId): array
    {
        $location = WarehouseLocation::query()->whereKey($locationId)->where('company_id', $companyId)->firstOrFail();
        $warehouse = Warehouse::query()->whereKey($location->warehouse_id)->where('company_id', $companyId)->firstOrFail();

        return [
            'payload' => [
                'location' => [
                    'id' => $location->getKey(),
                    'code' => $location->code,
                    'name' => $location->name,
                ],
                'warehouse' => [
                    'id' => $warehouse->getKey(),
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                ],
            ],
            'barcode_id' => null,
        ];
    }

    /** @return array{payload: array<string, mixed>, barcode_id: null} */
    private function shipment(int $companyId, int $dispatchId): array
    {
        $dispatch = Dispatch::query()->whereKey($dispatchId)->where('company_id', $companyId)->firstOrFail();
        $dispatchDate = $dispatch->getAttribute('dispatch_date');

        return [
            'payload' => [
                'shipment' => [
                    'id' => $dispatch->getKey(),
                    'number' => $dispatch->number,
                    'date' => $dispatchDate instanceof DateTimeInterface ? $dispatchDate->format('Y-m-d') : null,
                    'status' => $dispatch->statusEnum()->value,
                ],
            ],
            'barcode_id' => null,
        ];
    }
}
