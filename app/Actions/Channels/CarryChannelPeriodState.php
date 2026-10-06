<?php

namespace App\Actions\Channels;

use App\Enums\DocumentType;
use App\Models\Period;
use App\Models\SalesChannelAccount;
use App\Support\Audit\AuditContext;
use App\Support\Channels\ChannelExternalEventRegistryService;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use App\Support\Period\SourcePeriodContext;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

final class CarryChannelPeriodState
{
    public function __construct(
        private readonly ChannelExternalEventRegistryService $registry,
    ) {}

    /**
     * @param  array<int,int>  $salesOrderCarryMap  source sales_order id => target sales_order id
     * @return array{settings:int,listings:int,locations:int,order_snapshots:int}
     */
    public function handle(
        Period $sourcePeriod,
        array $salesOrderCarryMap,
        string $idempotencyKey,
    ): array {
        Gate::authorize('periods.update');
        PeriodContext::ensureWritable();

        $targetPeriod = Period::query()->findOrFail(PeriodContext::periodId());
        $this->assertPeriods($sourcePeriod, $targetPeriod);
        $salesOrderCarryMap = $this->normalizeOrderMap($salesOrderCarryMap);
        $snapshot = $this->sourceSnapshot($sourcePeriod, array_keys($salesOrderCarryMap));

        /** @var array{settings:int,listings:int,locations:int,order_snapshots:int,registry_rebinds:list<array<string,int|string>>} $result */
        $result = IdempotencyKey::runMaster(
            $idempotencyKey,
            'channels.carry:'.$sourcePeriod->id.':'.$targetPeriod->id,
            function () use (
                $sourcePeriod,
                $targetPeriod,
                $salesOrderCarryMap,
                $snapshot,
            ): array {
                $this->assertMasterAccounts($snapshot['account_ids'], (int) $targetPeriod->company_id);
                $this->assertTargetReferences($snapshot, $salesOrderCarryMap);

                $periodResult = DB::connection('period')->transaction(
                    fn (): array => $this->copyToTarget(
                        $salesOrderCarryMap,
                        $snapshot,
                    ),
                    attempts: 3,
                );

                foreach ($periodResult['registry_rebinds'] as $rebind) {
                    $account = SalesChannelAccount::query()
                        ->where('company_id', $targetPeriod->company_id)
                        ->findOrFail((int) $rebind['channel_account_id']);

                    $this->registry->rebindCarriedOrder(
                        account: $account,
                        externalId: (string) $rebind['external_order_id'],
                        sourcePeriodId: (int) $sourcePeriod->id,
                        sourceDocumentId: (int) $rebind['source_sales_order_id'],
                        targetPeriodId: (int) $targetPeriod->id,
                        targetDocumentId: (int) $rebind['target_sales_order_id'],
                    );
                }

                AuditContext::master(
                    'Kanal external-event registry carry yönlendirmesi tamamlandı.',
                    [
                        'source_period_id' => (int) $sourcePeriod->id,
                        'target_period_id' => (int) $targetPeriod->id,
                        'rebound_order_registry_count' => count($periodResult['registry_rebinds']),
                    ],
                    null,
                    'channel_registry_carried',
                );

                AuditContext::period(
                    'Kanal dönem devri snapshotları taşındı.',
                    [
                        'source_period_id' => (int) $sourcePeriod->id,
                        'target_period_id' => (int) $targetPeriod->id,
                        'settings' => $periodResult['settings'],
                        'listings' => $periodResult['listings'],
                        'locations' => $periodResult['locations'],
                        'order_snapshots' => $periodResult['order_snapshots'],
                    ],
                    null,
                    'channel_period_state_carried',
                );

                return $periodResult;
            },
        );

        unset($result['registry_rebinds']);

        return $result;
    }

    private function assertPeriods(Period $source, Period $target): void
    {
        if ((int) $source->company_id !== (int) $target->company_id) {
            throw new DomainException('Kanal devri yalnız aynı şirket dönemleri arasında yapılabilir.');
        }

        if ((int) $source->id === (int) $target->id
            || (int) $target->year !== (int) $source->year + 1) {
            throw new DomainException('Kanal carry yalnız bir sonraki şirket dönemine yapılabilir.');
        }

        if (! in_array((string) $source->status, ['active', 'closed'], true)) {
            throw new DomainException('Kanal carry source dönemi erişilebilir durumda değil.');
        }

        $userId = auth()->id();

        if ($userId === null || ! DB::connection('master')
            ->table('period_user_access')
            ->where('period_id', $source->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists()) {
            throw new AuthorizationException('Kaynak döneme erişim izni olmadan kanal carry yapılamaz.');
        }
    }

    /**
     * @param  array<int,int>  $map
     * @return array<int,int>
     */
    private function normalizeOrderMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $sourceId => $targetId) {
            $sourceId = (int) $sourceId;
            $targetId = (int) $targetId;

            if ($sourceId <= 0 || $targetId <= 0) {
                throw new DomainException('Kanal carry sales order map id değerleri pozitif olmalıdır.');
            }

            $normalized[$sourceId] = $targetId;
        }

        if (count($normalized) !== count(array_unique(array_values($normalized)))) {
            throw new DomainException('Bir target sales_order birden fazla source order ile eşlenemez.');
        }

        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param  list<int>  $sourceSalesOrderIds
     * @return array{
     *   settings:Collection<int,\stdClass>,
     *   listings:Collection<int,\stdClass>,
     *   locations:Collection<int,\stdClass>,
     *   order_snapshots:Collection<int,\stdClass>,
     *   account_ids:list<int>
     * }
     */
    private function sourceSnapshot(Period $sourcePeriod, array $sourceSalesOrderIds): array
    {
        SourcePeriodContext::usePeriod($sourcePeriod);

        try {
            foreach ([
                'channel_account_period_settings',
                'channel_product_listings',
                'channel_listing_locations',
                'channel_order_snapshots',
            ] as $table) {
                if (! Schema::connection('period_source')->hasTable($table)) {
                    throw new DomainException('Kaynak period kanal şeması eksik: '.$table);
                }
            }

            $source = DB::connection('period_source');
            $settings = $source->table('channel_account_period_settings')->orderBy('id')->get();
            $listings = $source->table('channel_product_listings')->orderBy('id')->get();
            $locations = $source->table('channel_listing_locations')->orderBy('id')->get();
            /** @var Collection<int,\stdClass> $orderSnapshots */
            $orderSnapshots = $sourceSalesOrderIds === []
                ? collect()
                : $source->table('channel_order_snapshots')
                    ->whereIn('sales_order_id', $sourceSalesOrderIds)
                    ->orderBy('id')
                    ->get()
                    ->values();

            $accountIds = array_values(collect()
                ->merge($settings->pluck('channel_account_id'))
                ->merge($listings->pluck('channel_account_id'))
                ->merge($orderSnapshots->pluck('channel_account_id'))
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->sort()
                ->values()
                ->all());

            return [
                'settings' => $settings,
                'listings' => $listings,
                'locations' => $locations,
                'order_snapshots' => $orderSnapshots,
                'account_ids' => $accountIds,
            ];
        } finally {
            SourcePeriodContext::clear();
        }
    }

    /** @param list<int> $accountIds */
    private function assertMasterAccounts(array $accountIds, int $companyId): void
    {
        if ($accountIds === []) {
            return;
        }

        $found = SalesChannelAccount::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $accountIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $missing = array_values(array_diff($accountIds, $found));

        if ($missing !== []) {
            throw new DomainException(
                'Kanal carry için Master account eksik veya başka şirkete ait: '.implode(', ', $missing),
            );
        }
    }

    /**
     * @param  array{
     *   settings:Collection<int,\stdClass>,
     *   listings:Collection<int,\stdClass>,
     *   locations:Collection<int,\stdClass>,
     *   order_snapshots:Collection<int,\stdClass>,
     *   account_ids:list<int>
     * }  $snapshot
     * @param  array<int,int>  $salesOrderCarryMap
     */
    private function assertTargetReferences(array $snapshot, array $salesOrderCarryMap): void
    {
        $this->assertIdsExist(
            'contacts',
            $snapshot['settings']->pluck('marketplace_customer_contact_id')->map(fn ($id): int => (int) $id)->all(),
            'marketplace müşteri carisi',
        );
        $this->assertIdsExist(
            'products',
            $snapshot['listings']->pluck('product_id')->map(fn ($id): int => (int) $id)->all(),
            'ürün',
        );
        $this->assertIdsExist(
            'locations',
            $snapshot['locations']->pluck('location_id')->map(fn ($id): int => (int) $id)->all(),
            'listing lokasyonu',
        );

        if ($salesOrderCarryMap === []) {
            return;
        }

        $orders = DB::connection('period')->table('documents')
            ->whereIn('id', array_values($salesOrderCarryMap))
            ->get(['id', 'document_type', 'status'])
            ->keyBy(fn (object $row): int => (int) $row->id);

        foreach ($salesOrderCarryMap as $sourceId => $targetId) {
            $order = $orders->get($targetId);

            if (! $order
                || (string) $order->document_type !== DocumentType::SalesOrder->value
                || (string) $order->status !== 'confirmed') {
                throw new DomainException(
                    "Kanal carry target sales_order geçersiz: source #{$sourceId} → target #{$targetId}.",
                );
            }
        }
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
                'Kanal carry için target period eksik '.$label.' id: '.implode(', ', $missing),
            );
        }
    }

    /**
     * @param  array<int,int>  $salesOrderCarryMap
     * @param  array{
     *   settings:Collection<int,\stdClass>,
     *   listings:Collection<int,\stdClass>,
     *   locations:Collection<int,\stdClass>,
     *   order_snapshots:Collection<int,\stdClass>,
     *   account_ids:list<int>
     * }  $snapshot
     * @return array{settings:int,listings:int,locations:int,order_snapshots:int,registry_rebinds:list<array<string,int|string>>}
     */
    private function copyToTarget(
        array $salesOrderCarryMap,
        array $snapshot,
    ): array {
        $this->assertTargetTableCompatible(
            'channel_account_period_settings',
            $snapshot['settings']->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );
        $this->assertTargetTableCompatible(
            'channel_product_listings',
            $snapshot['listings']->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );
        $this->assertTargetTableCompatible(
            'channel_listing_locations',
            $snapshot['locations']->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $sourceSnapshots = $snapshot['order_snapshots']->keyBy(
            fn (object $row): int => (int) $row->sales_order_id,
        );
        $allowedTargetSnapshotOrderIds = collect($salesOrderCarryMap)
            ->filter(fn (int $targetId, int $sourceId): bool => $sourceSnapshots->has($sourceId))
            ->values()
            ->map(fn ($id): int => (int) $id)
            ->all();
        $unexpectedTargetSnapshot = DB::connection('period')->table('channel_order_snapshots')
            ->when(
                $allowedTargetSnapshotOrderIds === [],
                fn ($query) => $query,
                fn ($query) => $query->whereNotIn('sales_order_id', $allowedTargetSnapshotOrderIds),
            )
            ->exists();

        if ($unexpectedTargetSnapshot) {
            throw new DomainException(
                'Target period channel carry öncesinde beklenmeyen channel_order_snapshot verisi içeriyor.',
            );
        }

        $now = now();

        foreach ($snapshot['settings'] as $row) {
            $this->insertExact('channel_account_period_settings', (int) $row->id, [
                'channel_account_id' => (int) $row->channel_account_id,
                'marketplace_customer_contact_id' => (int) $row->marketplace_customer_contact_id,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['channel_account_id', 'marketplace_customer_contact_id']);
        }
        $this->resetSequence('channel_account_period_settings');

        foreach ($snapshot['listings'] as $row) {
            $this->insertExact('channel_product_listings', (int) $row->id, [
                'channel_account_id' => (int) $row->channel_account_id,
                'product_id' => (int) $row->product_id,
                'external_product_id' => $row->external_product_id,
                'external_listing_id' => $row->external_listing_id,
                'external_sku' => $row->external_sku,
                'stock_mode' => $row->stock_mode,
                'max_channel_quantity' => $row->max_channel_quantity,
                'withhold_quantity' => $row->withhold_quantity,
                'fixed_quantity' => $row->fixed_quantity,
                'manual_quantity' => $row->manual_quantity,
                'lead_time_days' => $row->lead_time_days,
                'price_override' => $row->price_override,
                'title_override' => $row->title_override,
                'description_override' => $row->description_override,
                'image_collection' => $row->image_collection,
                'category_metadata' => $row->category_metadata,
                'is_active' => (bool) $row->is_active,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'channel_account_id',
                'product_id',
                'external_product_id',
                'external_listing_id',
                'external_sku',
                'stock_mode',
                'max_channel_quantity',
                'withhold_quantity',
                'fixed_quantity',
                'manual_quantity',
                'lead_time_days',
                'price_override',
                'title_override',
                'description_override',
                'image_collection',
                'category_metadata',
                'is_active',
            ]);
        }
        $this->resetSequence('channel_product_listings');

        foreach ($snapshot['locations'] as $row) {
            $this->insertExact('channel_listing_locations', (int) $row->id, [
                'channel_product_listing_id' => (int) $row->channel_product_listing_id,
                'location_id' => (int) $row->location_id,
                'created_at' => $now,
                'updated_at' => $now,
            ], ['channel_product_listing_id', 'location_id']);
        }
        $this->resetSequence('channel_listing_locations');

        $rebinds = [];
        $copiedSnapshots = 0;

        foreach ($salesOrderCarryMap as $sourceOrderId => $targetOrderId) {
            $sourceSnapshot = $sourceSnapshots->get($sourceOrderId);

            if (! $sourceSnapshot) {
                continue;
            }

            $existing = DB::connection('period')->table('channel_order_snapshots')
                ->where('sales_order_id', $targetOrderId)
                ->first();

            $values = [
                'sales_order_id' => $targetOrderId,
                'channel_account_id' => (int) $sourceSnapshot->channel_account_id,
                'external_order_id' => (string) $sourceSnapshot->external_order_id,
                'external_order_no' => $sourceSnapshot->external_order_no,
                'buyer_name' => null,
                'recipient_name' => null,
                'phone' => null,
                'email' => null,
                'address' => null,
                'city' => null,
                'district' => null,
                'postcode' => null,
                'cargo_company' => null,
                'cargo_code' => null,
                'external_shipment_id' => $sourceSnapshot->external_shipment_id,
                'external_package_id' => $sourceSnapshot->external_package_id,
                'campaign_metadata' => null,
            ];

            if ($existing) {
                foreach ($values as $key => $value) {
                    if ((string) ($existing->{$key} ?? '') !== (string) ($value ?? '')) {
                        throw new DomainException(
                            "Target channel_order_snapshot carry retry ile uyuşmuyor: sales_order #{$targetOrderId}.",
                        );
                    }
                }
            } else {
                DB::connection('period')->table('channel_order_snapshots')->insert([
                    ...$values,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $copiedSnapshots++;
            $rebinds[] = [
                'channel_account_id' => (int) $sourceSnapshot->channel_account_id,
                'external_order_id' => (string) $sourceSnapshot->external_order_id,
                'source_sales_order_id' => $sourceOrderId,
                'target_sales_order_id' => $targetOrderId,
            ];
        }

        if (DB::connection('period')->table('channel_sync_events')->exists()
            || DB::connection('period')->table('channel_sync_errors')->exists()) {
            throw new DomainException(
                'Kanal carry target period geçmiş sync event/error içeremez.',
            );
        }

        return [
            'settings' => $snapshot['settings']->count(),
            'listings' => $snapshot['listings']->count(),
            'locations' => $snapshot['locations']->count(),
            'order_snapshots' => $copiedSnapshots,
            'registry_rebinds' => $rebinds,
        ];
    }

    /** @param list<int> $allowedIds */
    private function assertTargetTableCompatible(string $table, array $allowedIds): void
    {
        $unexpected = DB::connection('period')->table($table)
            ->when(
                $allowedIds === [],
                fn ($query) => $query,
                fn ($query) => $query->whereNotIn('id', $allowedIds),
            )
            ->exists();

        if ($unexpected) {
            throw new DomainException(
                'Target period channel carry öncesinde beklenmeyen '.$table.' verisi içeriyor.',
            );
        }
    }

    /**
     * @param  array<string,mixed>  $values
     * @param  list<string>  $compareKeys
     */
    private function insertExact(
        string $table,
        int $id,
        array $values,
        array $compareKeys,
    ): void {
        $existing = DB::connection('period')->table($table)->where('id', $id)->first();

        if ($existing) {
            foreach ($compareKeys as $key) {
                $actual = $existing->{$key} ?? null;
                $expected = $values[$key] ?? null;

                if ($this->normalized($actual) !== $this->normalized($expected)) {
                    throw new DomainException(
                        "Target {$table} #{$id} source carry snapshotıyla uyuşmuyor.",
                    );
                }
            }

            return;
        }

        DB::connection('period')->table($table)->insert(['id' => $id, ...$values]);
    }

    private function normalized(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function resetSequence(string $table): void
    {
        if (! in_array($table, [
            'channel_account_period_settings',
            'channel_product_listings',
            'channel_listing_locations',
        ], true)) {
            throw new DomainException('Kanal carry sequence tablosu izinli değil.');
        }

        DB::connection('period')->statement(sprintf(
            "SELECT setval(
                pg_get_serial_sequence('%1\$s', 'id'),
                COALESCE((SELECT MAX(id) FROM %1\$s), 1),
                (SELECT MAX(id) IS NOT NULL FROM %1\$s)
            )",
            $table,
        ));
    }
}
