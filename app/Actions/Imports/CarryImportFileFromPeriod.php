<?php

namespace App\Actions\Imports;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Models\Period;
use App\Models\Period\ImportContainer;
use App\Models\Period\ImportCostItem;
use App\Models\Period\ImportFile;
use App\Models\Period\ImportPackage;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use App\Support\Period\SourcePeriodContext;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CarryImportFileFromPeriod
{
    public function __construct(private readonly GenerateDocumentNumber $numbers) {}

    public function handle(
        Period $sourcePeriod,
        int $sourceImportFileId,
        string $idempotencyKey,
        bool $asPeriodCarry = false,
    ): ImportFile {
        if ($asPeriodCarry) {
            Gate::authorize('periods.update');
        } else {
            MutationAuthorizer::authorize('import_shipments.create');
        }
        PeriodContext::ensureWritable();
        $this->assertSourcePeriod($sourcePeriod);

        $snapshot = $this->sourceSnapshot($sourcePeriod, $sourceImportFileId);

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'import.carry:'.$sourcePeriod->id.':'.$sourceImportFileId,
            fn (): int => DB::connection('period')->transaction(function () use (
                $sourcePeriod,
                $sourceImportFileId,
                $snapshot,
            ): int {
                $existing = ImportFile::query()
                    ->where('source_period_id', $sourcePeriod->id)
                    ->where('source_import_file_id', $sourceImportFileId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return (int) $existing->id;
                }

                $this->assertTargetReferences($snapshot);

                $sourceFile = $snapshot['file'];
                $currency = strtoupper((string) $sourceFile->currency);
                $actor = auth()->user();

                $target = ImportFile::query()->create([
                    'number' => $this->numbers->handle('import_file'),
                    'source_period_id' => (int) $sourcePeriod->id,
                    'source_import_file_id' => (int) $sourceFile->id,
                    'source_number' => (string) $sourceFile->number,
                    'supplier_contact_id' => (int) $sourceFile->supplier_contact_id,
                    'receiving_location_id' => $sourceFile->receiving_location_id !== null
                        ? (int) $sourceFile->receiving_location_id
                        : null,
                    'country' => $sourceFile->country,
                    'incoterm' => $sourceFile->incoterm,
                    'currency' => $currency,
                    'exchange_rate' => $currency === 'TRY' ? '1.000000' : null,
                    'exchange_rate_locked_at' => null,
                    'exchange_rate_date' => null,
                    'etd' => $sourceFile->etd,
                    'eta' => $sourceFile->eta,
                    'received_at' => null,
                    'status' => (string) $sourceFile->status,
                    'notes' => $sourceFile->notes,
                    'version' => 1,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                    'closed_by' => null,
                    'closed_by_name' => null,
                    'closed_at' => null,
                ]);

                $containerMap = [];

                foreach ($snapshot['containers'] as $container) {
                    if (! in_array((string) $container->status, ['planned', 'in_transit', 'customs'], true)) {
                        throw new DomainException('Açık ithalat devrinde teslim alınmış/kapanmış konteyner taşınamaz.');
                    }

                    $created = ImportContainer::query()->create([
                        'import_file_id' => $target->id,
                        'container_no' => (string) $container->container_no,
                        'container_type' => $container->container_type,
                        'seal_no' => $container->seal_no,
                        'gross_weight_kg' => $container->gross_weight_kg,
                        'volume_cbm' => $container->volume_cbm,
                        'etd' => $container->etd,
                        'eta' => $container->eta,
                        'status' => (string) $container->status,
                        'notes' => $container->notes,
                    ]);

                    $containerMap[(int) $container->id] = (int) $created->id;
                }

                foreach ($snapshot['packages'] as $package) {
                    if (! in_array((string) $package->status, ['unmatched', 'matched'], true)) {
                        throw new DomainException('Açık ithalat devrinde teslim alınmış paket taşınamaz.');
                    }

                    $targetContainerId = $containerMap[(int) $package->container_id] ?? null;

                    if ($targetContainerId === null) {
                        throw new DomainException('İthalat paketi için kaynak konteyner eşlemesi bulunamadı.');
                    }

                    ImportPackage::query()->create([
                        'import_file_id' => $target->id,
                        'container_id' => $targetContainerId,
                        'carton_no' => (string) $package->carton_no,
                        'component_name' => $package->component_name,
                        'product_id' => $package->product_id !== null ? (int) $package->product_id : null,
                        'location_id' => $package->location_id !== null ? (int) $package->location_id : null,
                        'quantity' => (string) $package->quantity,
                        'unit_price' => (string) $package->unit_price,
                        'weight_kg' => $package->weight_kg,
                        'volume_cbm' => $package->volume_cbm,
                        'goods_value_try' => null,
                        'allocated_cost_try' => null,
                        'landed_unit_cost_try' => null,
                        'status' => (string) $package->status,
                        'received_at' => null,
                        'notes' => $package->notes,
                    ]);
                }

                foreach ($snapshot['cost_items'] as $item) {
                    $itemCurrency = strtoupper((string) $item->currency);

                    ImportCostItem::query()->create([
                        'import_file_id' => $target->id,
                        'name' => (string) $item->name,
                        'amount' => (string) $item->amount,
                        'currency' => $itemCurrency,
                        'exchange_rate' => $itemCurrency === 'TRY' ? '1.000000' : null,
                        'amount_try' => null,
                        'allocation_basis' => (string) $item->allocation_basis,
                        'allocated_at' => null,
                        'notes' => $item->notes,
                    ]);
                }

                AuditContext::period(
                    'Açık ithalat dosyası önceki dönemden taşındı.',
                    [
                        'import_file_id' => $target->id,
                        'number' => $target->number,
                        'source_period_id' => (int) $sourcePeriod->id,
                        'source_import_file_id' => (int) $sourceFile->id,
                        'source_number' => (string) $sourceFile->number,
                    ],
                    $target,
                    'import_carried',
                );

                return (int) $target->id;
            }, attempts: 3),
        );

        return ImportFile::query()
            ->with(['packages.product', 'containers', 'costItems'])
            ->findOrFail((int) $id);
    }

    private function assertSourcePeriod(Period $sourcePeriod): void
    {
        if ((int) $sourcePeriod->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('İthalat devri yalnız aynı şirketin dönemleri arasında yapılabilir.');
        }

        if ((int) $sourcePeriod->id === (int) PeriodContext::periodId()
            || (int) $sourcePeriod->year >= (int) PeriodContext::year()) {
            throw new DomainException('Kaynak ithalat dönemi aktif hedef dönemden eski olmalıdır.');
        }

        if (! in_array((string) $sourcePeriod->status, ['active', 'closed'], true)) {
            throw new DomainException('Kaynak dönem ithalat devri için erişilebilir durumda değil.');
        }

        $userId = auth()->id();

        if ($userId === null || ! DB::connection('master')
            ->table('period_user_access')
            ->where('period_id', $sourcePeriod->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists()) {
            throw new AuthorizationException('Kaynak döneme erişim izni olmadan ithalat devri yapılamaz.');
        }
    }

    /** @return array{file:object,containers:Collection<int, object>,packages:Collection<int, object>,cost_items:Collection<int, object>} */
    private function sourceSnapshot(Period $sourcePeriod, int $sourceImportFileId): array
    {
        SourcePeriodContext::usePeriod($sourcePeriod);

        try {
            $connection = DB::connection('period_source');
            $file = $connection->table('import_files')->where('id', $sourceImportFileId)->first();

            if (! $file) {
                throw new DomainException('Kaynak dönemde ithalat dosyası bulunamadı.');
            }

            if (! in_array((string) $file->status, ['draft', 'in_transit', 'customs'], true)
                || $file->received_at !== null
                || $file->closed_at !== null) {
                throw new DomainException('Yalnız açık ve henüz stoğa alınmamış ithalat dosyası yeni döneme taşınabilir.');
            }

            return [
                'file' => $file,
                'containers' => $connection->table('containers')
                    ->where('import_file_id', $sourceImportFileId)
                    ->orderBy('id')
                    ->get(),
                'packages' => $connection->table('packages')
                    ->where('import_file_id', $sourceImportFileId)
                    ->orderBy('id')
                    ->get(),
                'cost_items' => $connection->table('import_cost_items')
                    ->where('import_file_id', $sourceImportFileId)
                    ->orderBy('id')
                    ->get(),
            ];
        } finally {
            SourcePeriodContext::clear();
        }
    }

    /** @param array{file:object,containers:Collection<int, object>,packages:Collection<int, object>,cost_items:Collection<int, object>} $snapshot */
    private function assertTargetReferences(array $snapshot): void
    {
        $file = $snapshot['file'];

        $this->assertIdsExist('contacts', [(int) $file->supplier_contact_id], 'tedarikçi');

        $locationIds = [];
        if ($file->receiving_location_id !== null) {
            $locationIds[] = (int) $file->receiving_location_id;
        }

        $productIds = [];
        foreach ($snapshot['packages'] as $package) {
            if ($package->location_id !== null) {
                $locationIds[] = (int) $package->location_id;
            }
            if ($package->product_id !== null) {
                $productIds[] = (int) $package->product_id;
            }
        }

        $this->assertIdsExist('locations', $locationIds, 'lokasyon');
        $this->assertIdsExist('products', $productIds, 'ürün');
    }

    /** @param list<int> $ids */
    private function assertIdsExist(string $table, array $ids, string $label): void
    {
        $ids = array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return;
        }

        $found = DB::connection('period')->table($table)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $missing = array_values(array_diff($ids, $found));

        if ($missing !== []) {
            throw new DomainException(
                'İthalat devri için hedef dönemde eksik '.$label.' kartı var: '.implode(', ', $missing),
            );
        }
    }
}
