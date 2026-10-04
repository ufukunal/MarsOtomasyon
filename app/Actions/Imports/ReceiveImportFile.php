<?php

namespace App\Actions\Imports;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Models\Period\ImportFile;
use App\Models\Period\ImportPackage;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Imports\ImportCostAllocator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReceiveImportFile
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly ImportCostAllocator $allocator,
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(
        ImportFile $file,
        string $receivingDate,
        string $idempotencyKey,
    ): ImportFile {
        MutationAuthorizer::authorize('import_shipments.receive');

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'import.receive:'.$file->id,
            fn (): int => DB::connection('period')->transaction(function () use (
                $file,
                $receivingDate,
            ): int {
                $locked = ImportFile::query()->lockForUpdate()->findOrFail($file->id);

                if (! in_array($locked->status, ['draft', 'in_transit', 'customs'], true)) {
                    throw new DomainException('İthalat dosyası stoğa alma için uygun durumda değil.');
                }

                $date = CarbonImmutable::parse($receivingDate)->startOfDay();
                $this->ensurePeriodOpen->handle($date);

                $rate = bcadd((string) ($locked->exchange_rate ?? '0'), '0', 6);

                if (bccomp($rate, '0', 6) <= 0) {
                    throw new DomainException('Stoğa almadan önce ithalat kuru girilmelidir.');
                }

                $packages = ImportPackage::query()
                    ->where('import_file_id', $locked->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($packages->isEmpty()) {
                    throw new DomainException('Stoğa alınacak ithalat paketi bulunmuyor.');
                }

                foreach ($packages as $package) {
                    if ($package->product_id === null || $package->location_id === null) {
                        throw new DomainException('Tüm ithalat kolileri ürün ve lokasyonla eşleştirilmelidir.');
                    }

                    if ($package->status !== 'matched') {
                        throw new DomainException('Tüm ithalat kolileri eşleşmiş durumda olmalıdır.');
                    }
                }

                $locked->exchange_rate = $rate;
                $locked->exchange_rate_date = $date->toDateString();
                $locked->exchange_rate_locked_at = now();
                $locked->save();

                $this->allocator->recalculate($locked);

                $actor = auth()->user();

                foreach ($packages as $package) {
                    $package->refresh();

                    if ($package->landed_unit_cost_try === null) {
                        throw new DomainException('İthalat landed cost hesaplanmadan stok girişi yapılamaz.');
                    }

                    $this->recordStockMovement->handle(new StockMovementData(
                        productId: (int) $package->product_id,
                        locationId: (int) $package->location_id,
                        movementDate: $date->toDateString(),
                        direction: 'in',
                        reason: 'import',
                        quantity: (string) $package->quantity,
                        unitCost: (string) $package->landed_unit_cost_try,
                        updatesAverage: true,
                        documentType: 'import_file',
                        documentId: (int) $locked->id,
                        documentNo: $locked->number,
                        note: $package->carton_no,
                        actorUserId: $actor?->id,
                        actorUserName: $actor?->name,
                    ));

                    $package->status = 'received';
                    $package->received_at = now();
                    $package->save();
                }

                $locked->containers()->update(['status' => 'received']);
                $locked->status = 'received';
                $locked->received_at = $date->toDateString();
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                AuditContext::period(
                    'İthalat dosyası stoğa alındı ve kur sabitlendi.',
                    [
                        'import_file_id' => $locked->id,
                        'number' => $locked->number,
                        'exchange_rate' => $rate,
                        'receiving_date' => $date->toDateString(),
                        'package_count' => $packages->count(),
                    ],
                    $locked,
                    'import_received',
                );

                return (int) $locked->id;
            }, attempts: 3),
        );

        return ImportFile::query()
            ->with(['packages.product', 'containers', 'costItems'])
            ->findOrFail((int) $id);
    }
}
