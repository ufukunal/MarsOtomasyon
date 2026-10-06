<?php

namespace App\Actions\Imports;

use App\Enums\ProductKind;
use App\Models\Period\ImportContainer;
use App\Models\Period\ImportCostAllocation;
use App\Models\Period\ImportFile;
use App\Models\Period\ImportPackage;
use App\Models\Period\Product;
use App\Support\Auth\MutationAuthorizer;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveImportPackage
{
    /** @param array<string,mixed> $data */
    public function handle(ImportFile $file, array $data, ?ImportPackage $package = null): ImportPackage
    {
        MutationAuthorizer::authorize('import_shipments.update');

        return DB::connection('period')->transaction(function () use ($file, $data, $package): ImportPackage {
            $lockedFile = ImportFile::query()->lockForUpdate()->findOrFail($file->id);

            if ($lockedFile->isLocked()) {
                throw new DomainException('Teslim alınmış ithalat dosyasında koli eşleşmesi değiştirilemez.');
            }

            $container = ImportContainer::query()
                ->where('import_file_id', $lockedFile->id)
                ->findOrFail((int) $data['container_id']);

            $model = $package
                ? ImportPackage::query()->lockForUpdate()->findOrFail($package->id)
                : new ImportPackage(['import_file_id' => $lockedFile->id]);

            if ($model->exists && (int) $model->import_file_id !== (int) $lockedFile->id) {
                throw new DomainException('Koli başka ithalat dosyasına ait.');
            }

            $quantity = bcadd((string) $data['quantity'], '0', 3);
            $unitPrice = bcadd((string) ($data['unit_price'] ?? '0'), '0', 4);

            if (bccomp($quantity, '0', 3) <= 0 || bccomp($unitPrice, '0', 4) < 0) {
                throw new DomainException('Koli miktarı/fiyatı geçersiz.');
            }

            $weight = ($data['weight_kg'] ?? null);
            $weight = $weight === null || trim((string) $weight) === ''
                ? null
                : bcadd((string) $weight, '0', 4);
            $volume = ($data['volume_cbm'] ?? null);
            $volume = $volume === null || trim((string) $volume) === ''
                ? null
                : bcadd((string) $volume, '0', 6);

            if (($weight !== null && bccomp($weight, '0', 4) < 0)
                || ($volume !== null && bccomp($volume, '0', 6) < 0)) {
                throw new DomainException('Koli ağırlık/hacim değerleri negatif olamaz.');
            }

            $productId = ($data['product_id'] ?? null) ? (int) $data['product_id'] : null;

            if ($productId !== null) {
                $product = Product::query()->findOrFail($productId);

                if ($product->kind === ProductKind::Set) {
                    throw new DomainException('Set ürün fiziksel ithalat paketiyle eşleştirilemez.');
                }
            }

            $model->fill([
                'container_id' => $container->id,
                'carton_no' => trim((string) $data['carton_no']),
                'component_name' => trim((string) ($data['component_name'] ?? '')) ?: null,
                'product_id' => $productId,
                'location_id' => ($data['location_id'] ?? null)
                    ? (int) $data['location_id']
                    : $lockedFile->receiving_location_id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'weight_kg' => $weight,
                'volume_cbm' => $volume,
                'status' => $productId ? 'matched' : 'unmatched',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'goods_value_try' => null,
                'allocated_cost_try' => null,
                'landed_unit_cost_try' => null,
            ]);

            if ($model->carton_no === '') {
                throw new DomainException('Koli numarası zorunludur.');
            }

            $model->save();

            ImportCostAllocation::query()
                ->whereIn('import_cost_item_id', $lockedFile->costItems()->pluck('id'))
                ->delete();
            $lockedFile->costItems()->update(['amount_try' => null, 'allocated_at' => null]);
            $lockedFile->packages()->update([
                'goods_value_try' => null,
                'allocated_cost_try' => null,
                'landed_unit_cost_try' => null,
            ]);

            return $model->refresh();
        }, attempts: 3);
    }
}
