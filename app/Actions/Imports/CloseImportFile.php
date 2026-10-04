<?php

namespace App\Actions\Imports;

use App\Models\Period\ImportFile;
use App\Models\Period\ProductCost;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CloseImportFile
{
    public function handle(ImportFile $file, string $idempotencyKey): ImportFile
    {
        MutationAuthorizer::authorize('import_shipments.close');

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'import.close:'.$file->id,
            fn (): int => DB::connection('period')->transaction(function () use ($file): int {
                $locked = ImportFile::query()
                    ->with('packages')
                    ->lockForUpdate()
                    ->findOrFail($file->id);

                if ($locked->status !== 'received' || $locked->exchange_rate_locked_at === null) {
                    throw new DomainException('Yalnız stoğa alınmış ve kuru sabitlenmiş ithalat dosyası kapatılabilir.');
                }

                if ($locked->packages->isEmpty()
                    || $locked->packages->contains(fn ($package) => $package->status !== 'received'
                        || $package->landed_unit_cost_try === null
                        || $package->product_id === null)) {
                    throw new DomainException('İthalat kapanışı için tüm paketler teslim alınmış ve maliyetlenmiş olmalıdır.');
                }

                $grouped = $locked->packages->groupBy('product_id');
                $importCosts = [];

                foreach ($grouped as $productId => $packages) {
                    $qty = '0.000';
                    $value = '0.0000';

                    foreach ($packages as $package) {
                        $qty = bcadd($qty, (string) $package->quantity, 3);
                        $value = bcadd(
                            $value,
                            bcmul((string) $package->quantity, (string) $package->landed_unit_cost_try, 8),
                            4,
                        );
                    }

                    if (bccomp($qty, '0', 3) <= 0) {
                        throw new DomainException('Ürün ithalat miktarı sıfır olamaz.');
                    }

                    $importCosts[(int) $productId] = bcdiv($value, $qty, 4);
                }

                $actor = auth()->user();

                // Child model guard'ını yalnız kontrollü receive -> closed yaşam döngüsü için aşar.
                $locked->containers()
                    ->where('status', 'received')
                    ->update(['status' => 'closed', 'updated_at' => now()]);

                $locked->status = 'closed';
                $locked->closed_by = $actor?->id;
                $locked->closed_by_name = $actor?->name;
                $locked->closed_at = now();
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                ksort($importCosts, SORT_NUMERIC);

                foreach ($importCosts as $productId => $unitCost) {
                    DB::connection('period')->table('product_costs')->insertOrIgnore([
                        'product_id' => $productId,
                        'last_purchase_price' => '0.0000',
                        'moving_average' => '0.0000',
                        'import_cost' => '0.0000',
                        'production_cost' => '0.0000',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $cost = ProductCost::query()
                        ->where('product_id', $productId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $latestClosedFileId = ImportFile::query()
                        ->where('status', 'closed')
                        ->whereHas('packages', fn ($query) => $query->where('product_id', $productId))
                        ->orderByDesc('closed_at')
                        ->orderByDesc('id')
                        ->value('id');

                    if ((int) $latestClosedFileId === (int) $locked->id) {
                        $cost->setAttribute('import_cost', $unitCost);
                        $cost->save();
                    }
                }

                AuditContext::period(
                    'İthalat dosyası kapatıldı; ürün ithalat maliyetleri güncellendi.',
                    [
                        'import_file_id' => $locked->id,
                        'number' => $locked->number,
                        'product_count' => $grouped->count(),
                    ],
                    $locked,
                    'import_closed',
                );

                return (int) $locked->id;
            }, attempts: 3),
        );

        return ImportFile::query()->with(['packages.product', 'containers', 'costItems'])->findOrFail((int) $id);
    }
}
