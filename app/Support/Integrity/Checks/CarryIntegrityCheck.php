<?php

namespace App\Support\Integrity\Checks;

use App\Actions\Documents\SourceLineAvailability;
use App\Actions\Purchases\PurchaseLineAvailability;
use App\Enums\DocumentType;
use App\Models\Period;
use App\Models\Period\Document;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use App\Support\Period\PeriodContext;
use App\Support\PeriodCarry\PeriodCarryTableCopier;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CarryIntegrityCheck implements IntegrityCheck
{
    public function __construct(
        private readonly SourceLineAvailability $salesAvailability,
        private readonly PurchaseLineAvailability $purchaseAvailability,
        private readonly PeriodCarryTableCopier $copier,
    ) {}

    public function name(): string
    {
        return 'carry';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);
        PeriodContext::ensure();

        $target = Period::query()->findOrFail(PeriodContext::periodId());
        $source = $target->carried_from_period_id
            ? Period::query()->find((int) $target->carried_from_period_id)
            : null;

        if (! $source) {
            return new IntegrityResult(
                checked: 1,
                mismatches: [['reason' => 'source_period_missing']],
                durationMs: $this->elapsed($started),
            );
        }

        $mismatches = [];
        $checked = 0;
        $targetProductIds = $this->targetIds('products');
        $targetContactIds = $this->targetIds('contacts');
        $targetLocationIds = $this->targetIds('locations');
        $targetCashIds = $this->targetIds('cash_accounts');
        $targetBankIds = $this->targetIds('bank_accounts');

        $sourceData = PeriodContext::withinSystem(
            $source,
            fn (): array => $this->sourceSnapshot(
                $source,
                $targetProductIds,
                $targetContactIds,
                $targetLocationIds,
                $targetCashIds,
                $targetBankIds,
            ),
        );

        foreach ($sourceData['stock_balances'] as $id => $expected) {
            $actual = DB::connection('period')->table('stock_balances')->where('id', $id)->first();
            $checked++;

            if (! $actual) {
                $mismatches[] = ['reason' => 'stock_balance_missing', 'id' => $id];

                continue;
            }

            foreach (['product_id', 'location_id'] as $field) {
                if ((int) $actual->{$field} !== (int) $expected[$field]) {
                    $mismatches[] = [
                        'reason' => 'stock_balance_identity_mismatch',
                        'id' => $id,
                        'field' => $field,
                        'expected' => $expected[$field],
                        'actual' => $actual->{$field},
                    ];
                }
            }

            foreach (['quantity', 'consignment_reserved', 'quarantine'] as $field) {
                if (bccomp((string) $actual->{$field}, (string) $expected[$field], 3) !== 0) {
                    $mismatches[] = [
                        'reason' => 'stock_balance_value_mismatch',
                        'id' => $id,
                        'field' => $field,
                        'expected' => $expected[$field],
                        'actual' => $actual->{$field},
                    ];
                }
            }

            $reserved = (string) (DB::connection('period')->table('stock_reservations')
                ->where('product_id', $actual->product_id)
                ->where('location_id', $actual->location_id)
                ->where('status', 'active')
                ->sum('quantity') ?? '0');

            if (bccomp((string) $actual->reserved, $reserved, 3) !== 0) {
                $mismatches[] = [
                    'reason' => 'reserved_summary_mismatch',
                    'id' => $id,
                    'expected' => bcadd($reserved, '0', 3),
                    'actual' => (string) $actual->reserved,
                ];
            }
        }

        foreach ($sourceData['product_costs'] as $productId => $expected) {
            $actual = DB::connection('period')->table('product_costs')->where('product_id', $productId)->first();
            $checked++;

            if (! $actual) {
                $mismatches[] = ['reason' => 'product_cost_missing', 'product_id' => $productId];

                continue;
            }

            foreach (['last_purchase_price', 'moving_average', 'import_cost', 'production_cost'] as $field) {
                if (bccomp((string) $actual->{$field}, (string) $expected[$field], 4) !== 0) {
                    $mismatches[] = [
                        'reason' => 'product_cost_mismatch',
                        'product_id' => $productId,
                        'field' => $field,
                        'expected' => $expected[$field],
                        'actual' => $actual->{$field},
                    ];
                }
            }
        }

        $checked += $this->compareBalanceMap(
            'contact_opening',
            $sourceData['contact_balances'],
            $this->targetContactBalances(),
            $mismatches,
        );
        $checked += $this->compareBalanceMap(
            'cash_opening',
            $sourceData['cash_balances'],
            $this->targetFinancialBalances('cash_movements', 'cash_account_id'),
            $mismatches,
        );
        $checked += $this->compareBalanceMap(
            'bank_opening',
            $sourceData['bank_balances'],
            $this->targetFinancialBalances('bank_movements', 'bank_account_id'),
            $mismatches,
        );

        $checked += $this->compareIdSet(
            'security',
            $sourceData['security_ids'],
            $this->targetIds('securities'),
            $mismatches,
        );

        foreach ($sourceData['quarantine'] as $id => $expectedOpen) {
            $actual = DB::connection('period')->table('quarantine_entries')->where('id', $id)->first();
            $checked++;

            if (! $actual || bccomp((string) $actual->quantity, $expectedOpen, 3) !== 0
                || bccomp((string) $actual->released_quantity, '0', 3) !== 0
                || bccomp((string) $actual->scrapped_quantity, '0', 3) !== 0) {
                $mismatches[] = [
                    'reason' => 'quarantine_opening_mismatch',
                    'id' => $id,
                    'expected_open_quantity' => $expectedOpen,
                ];
            }
        }

        $targetImportSourceIds = DB::connection('period')->table('import_files')
            ->where('source_period_id', $source->id)
            ->pluck('source_import_file_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $checked += $this->compareIdSet(
            'import_carry',
            $sourceData['import_ids'],
            $targetImportSourceIds,
            $mismatches,
        );

        $targetCarryRows = DB::connection('period')->table('period_document_carries')->get();
        $salesSourceIds = $targetCarryRows
            ->where('document_type', DocumentType::SalesOrder->value)
            ->pluck('source_document_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $purchaseSourceIds = $targetCarryRows
            ->where('document_type', DocumentType::PurchaseOrder->value)
            ->pluck('source_document_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $checked += $this->compareIdSet(
            'sales_order_carry',
            $sourceData['sales_order_ids'],
            $salesSourceIds,
            $mismatches,
        );
        $checked += $this->compareIdSet(
            'purchase_order_carry',
            $sourceData['purchase_order_ids'],
            $purchaseSourceIds,
            $mismatches,
        );

        foreach ($targetCarryRows as $carry) {
            $targetDocument = DB::connection('period')->table('documents')
                ->where('id', $carry->target_document_id)
                ->first();
            $checked++;

            if (! $targetDocument || (string) $targetDocument->document_type !== (string) $carry->document_type) {
                $mismatches[] = [
                    'reason' => 'carried_document_provenance_mismatch',
                    'carry_id' => (int) $carry->id,
                ];
            }
        }

        $strayDocuments = DB::connection('period')->table('documents')
            ->leftJoin(
                'period_document_carries',
                'period_document_carries.target_document_id',
                '=',
                'documents.id',
            )
            ->whereNull('period_document_carries.id')
            ->count();

        if ($strayDocuments > 0) {
            $mismatches[] = [
                'reason' => 'historical_or_untracked_documents_present',
                'count' => $strayDocuments,
            ];
        }
        $checked++;

        foreach ([
            'stock_movements' => fn () => DB::connection('period')->table('stock_movements')
                ->where('reason', '<>', 'opening')->count(),
            'contact_transactions' => fn () => DB::connection('period')->table('contact_transactions')
                ->where('transaction_type', '<>', 'opening')->count(),
            'cash_movements' => fn () => DB::connection('period')->table('cash_movements')
                ->where('movement_type', '<>', 'opening')->count(),
            'bank_movements' => fn () => DB::connection('period')->table('bank_movements')
                ->where('movement_type', '<>', 'opening')->count(),
            'transfers' => fn () => DB::connection('period')->table('transfers')->count(),
            'production_orders' => fn () => DB::connection('period')->table('production_orders')->count(),
            'channel_sync_events' => fn () => DB::connection('period')->table('channel_sync_events')->count(),
            'channel_sync_errors' => fn () => DB::connection('period')->table('channel_sync_errors')->count(),
            'document_print_snapshots' => fn () => DB::connection('period')->table('document_print_snapshots')->count(),
        ] as $table => $counter) {
            $count = $counter();
            $checked++;

            if ($count > 0) {
                $mismatches[] = [
                    'reason' => 'history_should_not_be_carried',
                    'table' => $table,
                    'count' => $count,
                ];
            }
        }

        $checked += $this->compareIdSet(
            'production_recipe',
            $sourceData['recipe_ids'],
            $this->targetIds('production_recipes'),
            $mismatches,
        );
        $checked += $this->compareIdSet(
            'channel_setting',
            $sourceData['channel_setting_ids'],
            $this->targetIds('channel_account_period_settings'),
            $mismatches,
        );
        $checked += $this->compareIdSet(
            'channel_listing',
            $sourceData['channel_listing_ids'],
            $this->targetIds('channel_product_listings'),
            $mismatches,
        );
        $checked += $this->compareIdSet(
            'channel_location',
            $sourceData['channel_location_ids'],
            $this->targetIds('channel_listing_locations'),
            $mismatches,
        );

        foreach ([
            'units', 'unit_conversions', 'product_categories', 'brands', 'variant_groups',
            'price_lists', 'contacts', 'contact_categories', 'contact_addresses',
            'contact_people', 'contact_banks', 'locations', 'products', 'variant_attributes',
            'product_variant_values', 'product_sets', 'config_definitions', 'config_options',
            'price_list_items', 'cash_accounts', 'bank_accounts', 'stock_balances',
            'product_costs', 'securities', 'quarantine_entries', 'production_recipes',
            'production_recipe_lines', 'channel_account_period_settings',
            'channel_product_listings', 'channel_listing_locations',
        ] as $table) {
            try {
                $this->copier->assertSequence($table);
            } catch (Throwable $exception) {
                $mismatches[] = [
                    'reason' => 'sequence_mismatch',
                    'table' => $table,
                    'detail' => $exception->getMessage(),
                ];
            }
            $checked++;
        }

        return new IntegrityResult(
            checked: $checked,
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
            meta: [
                'source_period_id' => (int) $source->id,
                'target_period_id' => (int) $target->id,
            ],
        );
    }

    /**
     * @param  list<int>  $productIds
     * @param  list<int>  $contactIds
     * @param  list<int>  $locationIds
     * @param  list<int>  $cashIds
     * @param  list<int>  $bankIds
     * @return array<string,mixed>
     */
    private function sourceSnapshot(
        Period $source,
        array $productIds,
        array $contactIds,
        array $locationIds,
        array $cashIds,
        array $bankIds,
    ): array {
        $stockBalances = DB::connection('period')->table('stock_balances')
            ->whereIn('product_id', $productIds ?: [-1])
            ->whereIn('location_id', $locationIds ?: [-1])
            ->where(function ($query): void {
                $query->where('quantity', '<>', 0)
                    ->orWhere('reserved', '<>', 0)
                    ->orWhere('consignment_reserved', '<>', 0)
                    ->orWhere('quarantine', '<>', 0);
            })
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->id => [
                    'product_id' => (int) $row->product_id,
                    'location_id' => (int) $row->location_id,
                    'quantity' => (string) $row->quantity,
                    'consignment_reserved' => (string) $row->consignment_reserved,
                    'quarantine' => (string) $row->quarantine,
                ],
            ])
            ->all();

        $productCosts = DB::connection('period')->table('product_costs')
            ->whereIn('product_id', $productIds ?: [-1])
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->product_id => [
                    'last_purchase_price' => (string) $row->last_purchase_price,
                    'moving_average' => (string) $row->moving_average,
                    'import_cost' => (string) $row->import_cost,
                    'production_cost' => (string) $row->production_cost,
                ],
            ])
            ->all();

        return [
            'stock_balances' => $stockBalances,
            'product_costs' => $productCosts,
            'contact_balances' => $this->sourceContactBalances($contactIds),
            'cash_balances' => $this->sourceFinancialBalances('cash_movements', 'cash_account_id', $cashIds),
            'bank_balances' => $this->sourceFinancialBalances('bank_movements', 'bank_account_id', $bankIds),
            'security_ids' => DB::connection('period')->table('securities')
                ->whereIn('status', ['portfolio', 'issued', 'endorsed', 'banked'])
                ->where('due_date', '>', $source->ends_on->toDateString())
                ->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'quarantine' => DB::connection('period')->table('quarantine_entries')
                ->whereIn('status', ['pending', 'partial'])
                ->get()
                ->mapWithKeys(function (object $row): array {
                    $open = bcsub(
                        bcsub((string) $row->quantity, (string) $row->released_quantity, 3),
                        (string) $row->scrapped_quantity,
                        3,
                    );

                    return bccomp($open, '0', 3) > 0 ? [(int) $row->id => $open] : [];
                })
                ->all(),
            'import_ids' => DB::connection('period')->table('import_files')
                ->whereIn('status', ['draft', 'in_transit', 'customs'])
                ->whereNull('received_at')
                ->whereNull('closed_at')
                ->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'sales_order_ids' => $this->openOrderIds(DocumentType::SalesOrder),
            'purchase_order_ids' => $this->openOrderIds(DocumentType::PurchaseOrder),
            'recipe_ids' => DB::connection('period')->table('production_recipes')
                ->where('is_active', true)
                ->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'channel_setting_ids' => $this->tableIds('channel_account_period_settings'),
            'channel_listing_ids' => $this->tableIds('channel_product_listings'),
            'channel_location_ids' => $this->tableIds('channel_listing_locations'),
        ];
    }

    /** @return list<int> */
    private function openOrderIds(DocumentType $type): array
    {
        $statuses = $type === DocumentType::SalesOrder
            ? ['confirmed']
            : ['approved', 'sent'];

        return Document::query()
            ->with('lines')
            ->where('document_type', $type->value)
            ->whereIn('status', $statuses)
            ->orderBy('id')
            ->get()
            ->filter(function (Document $document) use ($type): bool {
                foreach ($document->lines as $line) {
                    $remaining = $type === DocumentType::SalesOrder
                        ? $this->salesAvailability->orderRemaining($line)
                        : bcsub(
                            $this->purchaseAvailability->orderReceiptRemaining($line),
                            (string) $line->cancelled_quantity,
                            3,
                        );

                    if (bccomp($remaining, '0', 3) > 0) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @param list<int> $contactIds @return array<string,string> */
    private function sourceContactBalances(array $contactIds): array
    {
        if ($contactIds === []) {
            return [];
        }

        return DB::connection('period')->table('contact_transactions')
            ->selectRaw("contact_id, currency, SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) AS balance")
            ->whereIn('contact_id', $contactIds)
            ->groupBy('contact_id', 'currency')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                ((int) $row->contact_id).'|'.(string) $row->currency => bcadd((string) $row->balance, '0', 4),
            ])
            ->filter(fn (string $value): bool => bccomp($value, '0', 4) !== 0)
            ->all();
    }

    /** @param list<int> $accountIds @return array<int|string,string> */
    private function sourceFinancialBalances(string $table, string $foreignKey, array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return DB::connection('period')->table($table)
            ->selectRaw($foreignKey.", SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) AS balance")
            ->whereIn($foreignKey, $accountIds)
            ->groupBy($foreignKey)
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (string) ((int) $row->{$foreignKey}) => bcadd((string) $row->balance, '0', 4),
            ])
            ->filter(fn (string $value): bool => bccomp($value, '0', 4) !== 0)
            ->all();
    }

    /** @return array<string,string> */
    private function targetContactBalances(): array
    {
        return DB::connection('period')->table('contact_transactions')
            ->selectRaw("contact_id, currency, SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) AS balance")
            ->groupBy('contact_id', 'currency')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                ((int) $row->contact_id).'|'.(string) $row->currency => bcadd((string) $row->balance, '0', 4),
            ])
            ->filter(fn (string $value): bool => bccomp($value, '0', 4) !== 0)
            ->all();
    }

    /** @return array<int|string,string> */
    private function targetFinancialBalances(string $table, string $foreignKey): array
    {
        return DB::connection('period')->table($table)
            ->selectRaw($foreignKey.", SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) AS balance")
            ->groupBy($foreignKey)
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (string) ((int) $row->{$foreignKey}) => bcadd((string) $row->balance, '0', 4),
            ])
            ->filter(fn (string $value): bool => bccomp($value, '0', 4) !== 0)
            ->all();
    }

    /**
     * @param  array<int|string,string>  $expected
     * @param  array<int|string,string>  $actual
     * @param  array<int,array<string,mixed>>  $mismatches
     */
    private function compareBalanceMap(
        string $label,
        array $expected,
        array $actual,
        array &$mismatches,
    ): int {
        $keys = array_values(array_unique([...array_keys($expected), ...array_keys($actual)]));

        foreach ($keys as $key) {
            $expectedValue = $expected[$key] ?? '0';
            $actualValue = $actual[$key] ?? '0';

            if (bccomp($expectedValue, $actualValue, 4) !== 0) {
                $mismatches[] = [
                    'reason' => $label.'_mismatch',
                    'key' => $key,
                    'expected' => $expectedValue,
                    'actual' => $actualValue,
                ];
            }
        }

        return count($keys);
    }

    /**
     * @param  list<int>  $expected
     * @param  list<int>  $actual
     * @param  array<int,array<string,mixed>>  $mismatches
     */
    private function compareIdSet(
        string $label,
        array $expected,
        array $actual,
        array &$mismatches,
    ): int {
        sort($expected, SORT_NUMERIC);
        sort($actual, SORT_NUMERIC);

        if ($expected !== $actual) {
            $mismatches[] = [
                'reason' => $label.'_id_set_mismatch',
                'expected' => $expected,
                'actual' => $actual,
            ];
        }

        return max(count($expected), count($actual), 1);
    }

    /** @return list<int> */
    private function targetIds(string $table): array
    {
        return DB::connection('period')->table($table)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function tableIds(string $table): array
    {
        return DB::connection('period')->table($table)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
