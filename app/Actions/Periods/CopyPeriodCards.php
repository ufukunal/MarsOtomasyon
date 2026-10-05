<?php

namespace App\Actions\Periods;

use App\Models\Period;
use App\Support\Period\PeriodContext;
use App\Support\Period\SourcePeriodContext;
use App\Support\PeriodCarry\PeriodCarryTableCopier;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CopyPeriodCards
{
    public function __construct(private readonly PeriodCarryTableCopier $copier) {}

    /** @return array<string,int> */
    public function handle(Period $source, Period $target): array
    {
        $this->assertPeriods($source, $target);
        PeriodContext::ensure();

        if ((int) PeriodContext::periodId() !== (int) $target->id) {
            throw new DomainException('CopyPeriodCards hedef period context içinde çalışmalıdır.');
        }

        SourcePeriodContext::usePeriod($source);

        try {
            $sourceDb = DB::connection('period_source');
            $productIds = $this->requiredProductIds();
            $locationIds = $this->requiredLocationIds();
            $contactIds = $this->requiredContactIds($locationIds);

            $unitIds = $this->ids($sourceDb->table('units')->where('is_active', true)->pluck('id'))
                + $this->foreignIds('products', 'unit_id', $productIds)
                + $this->foreignIds('production_recipe_lines', 'unit_id', $this->activeRecipeLineIds(), 'id')
                + $this->foreignIdsForOpenDocumentLines('unit_id');

            $categoryIds = $this->ids($sourceDb->table('product_categories')->where('is_active', true)->pluck('id'))
                + $this->foreignIds('products', 'category_id', $productIds);
            $categoryIds = $this->withCategoryParents($categoryIds);

            $brandIds = $this->ids($sourceDb->table('brands')->where('is_active', true)->pluck('id'))
                + $this->foreignIds('products', 'brand_id', $productIds);

            $variantGroupIds = $this->ids($sourceDb->table('variant_groups')->where('is_active', true)->pluck('id'))
                + $this->foreignIds('products', 'variant_group_id', $productIds);

            $priceListIds = $this->ids($sourceDb->table('price_lists')->where('is_active', true)->pluck('id'))
                + $this->foreignIds('contacts', 'price_list_id', $contactIds);

            $contactCategoryIds = $this->ids($sourceDb->table('contact_categories')->where('is_active', true)->pluck('id'));
            if ($contactIds !== []) {
                $contactCategoryIds += $this->ids(
                    $sourceDb->table('contact_category')
                        ->whereIn('contact_id', array_keys($contactIds))
                        ->pluck('contact_category_id'),
                );
            }

            $cashIds = $this->requiredFinancialAccountIds('cash_accounts', 'cash_movements', 'cash_account_id');
            $bankIds = $this->requiredFinancialAccountIds('bank_accounts', 'bank_movements', 'bank_account_id');
            $bankIds += $this->ids(
                $sourceDb->table('securities')
                    ->whereIn('status', ['portfolio', 'issued', 'endorsed', 'banked'])
                    ->whereNotNull('bank_account_id')
                    ->pluck('bank_account_id'),
            );

            $counts = [];
            $counts['units'] = $this->copier->copyIds('units', array_keys($unitIds), ['code']);
            $counts['product_categories'] = $this->copyCategories(array_keys($categoryIds));
            $counts['brands'] = $this->copier->copyIds('brands', array_keys($brandIds), ['name']);
            $counts['variant_groups'] = $this->copier->copyIds('variant_groups', array_keys($variantGroupIds));
            $counts['price_lists'] = $this->copier->copyIds('price_lists', array_keys($priceListIds));

            $counts['contacts'] = $this->copier->copyIds('contacts', array_keys($contactIds), ['code']);
            $counts['contact_categories'] = $this->copier->copyIds(
                'contact_categories',
                array_keys($contactCategoryIds),
                ['name'],
            );
            $counts['contact_category'] = $this->copyContactCategoryPivot(array_keys($contactIds), array_keys($contactCategoryIds));
            $counts['contact_addresses'] = $this->copyDependentIds('contact_addresses', 'contact_id', array_keys($contactIds));
            $counts['contact_people'] = $this->copyDependentIds('contact_people', 'contact_id', array_keys($contactIds));
            $counts['contact_banks'] = $this->copyDependentIds('contact_banks', 'contact_id', array_keys($contactIds));

            $counts['locations'] = $this->copier->copyIds('locations', array_keys($locationIds), ['code']);
            $counts['products'] = $this->copier->copyIds('products', array_keys($productIds), ['code']);

            $counts['unit_conversions'] = $this->copyUnitConversions(array_keys($unitIds));
            $counts['variant_attributes'] = $this->copyDependentIds(
                'variant_attributes',
                'variant_group_id',
                array_keys($variantGroupIds),
            );
            $counts['product_variant_values'] = $this->copyDependentIds(
                'product_variant_values',
                'product_id',
                array_keys($productIds),
            );
            $counts['product_sets'] = $this->copyProductRelationRows('product_sets', $productIds);
            $counts['config_definitions'] = $this->copyDependentIds(
                'config_definitions',
                'product_id',
                array_keys($productIds),
            );
            $configDefinitionIds = $this->ids(
                $sourceDb->table('config_definitions')
                    ->whereIn('product_id', array_keys($productIds))
                    ->pluck('id'),
            );
            $counts['config_options'] = $this->copyDependentIds(
                'config_options',
                'config_definition_id',
                array_keys($configDefinitionIds),
            );
            $counts['price_list_items'] = $this->copyPriceListItems(
                array_keys($priceListIds),
                array_keys($productIds),
            );

            $counts['cash_accounts'] = $this->copier->copyIds('cash_accounts', array_keys($cashIds), ['code']);
            $counts['bank_accounts'] = $this->copier->copyIds('bank_accounts', array_keys($bankIds), ['code']);

            return $counts;
        } finally {
            SourcePeriodContext::clear();
        }
    }

    /** @return array<int,true> */
    private function requiredProductIds(): array
    {
        $db = DB::connection('period_source');
        $ids = $this->ids($db->table('products')->where('is_active', true)->pluck('id'));

        $ids += $this->ids($db->table('stock_balances')
            ->where(function ($query): void {
                $query->where('quantity', '<>', 0)
                    ->orWhere('reserved', '<>', 0)
                    ->orWhere('consignment_reserved', '<>', 0)
                    ->orWhere('quarantine', '<>', 0);
            })
            ->pluck('product_id'));

        $ids += $this->ids($db->table('stock_reservations')->where('status', 'active')->pluck('product_id'));
        $ids += $this->ids($db->table('quarantine_entries')->whereIn('status', ['pending', 'partial'])->pluck('product_id'));
        $ids += $this->foreignIdsForOpenDocumentLines('product_id');
        $ids += $this->ids($db->table('packages')
            ->join('import_files', 'import_files.id', '=', 'packages.import_file_id')
            ->whereIn('import_files.status', ['draft', 'in_transit', 'customs'])
            ->whereNull('import_files.received_at')
            ->whereNotNull('packages.product_id')
            ->pluck('packages.product_id'));
        $ids += $this->ids($db->table('production_recipes')->where('is_active', true)->pluck('product_id'));
        $ids += $this->ids($db->table('production_recipe_lines')
            ->join('production_recipes', 'production_recipes.id', '=', 'production_recipe_lines.production_recipe_id')
            ->where('production_recipes.is_active', true)
            ->pluck('production_recipe_lines.component_product_id'));
        $ids += $this->ids($db->table('channel_product_listings')->pluck('product_id'));
        $ids += $this->ids($db->table('price_list_items')
            ->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
            ->where('price_lists.is_active', true)
            ->pluck('price_list_items.product_id'));

        do {
            $before = count($ids);

            if ($ids !== []) {
                $ids += $this->ids($db->table('product_sets')
                    ->whereIn('set_product_id', array_keys($ids))
                    ->pluck('component_product_id'));

                $ids += $this->ids($db->table('config_options')
                    ->join('config_definitions', 'config_definitions.id', '=', 'config_options.config_definition_id')
                    ->whereIn('config_definitions.product_id', array_keys($ids))
                    ->whereNotNull('config_options.component_product_id')
                    ->pluck('config_options.component_product_id'));
            }
        } while (count($ids) > $before);

        return $ids;
    }

    /** @return array<int,true> */
    private function requiredLocationIds(): array
    {
        $db = DB::connection('period_source');
        $ids = $this->ids($db->table('locations')->where('is_active', true)->pluck('id'));
        $ids += $this->ids($db->table('stock_balances')
            ->where(function ($query): void {
                $query->where('quantity', '<>', 0)
                    ->orWhere('reserved', '<>', 0)
                    ->orWhere('consignment_reserved', '<>', 0)
                    ->orWhere('quarantine', '<>', 0);
            })
            ->pluck('location_id'));
        $ids += $this->ids($db->table('stock_reservations')->where('status', 'active')->pluck('location_id'));
        $ids += $this->ids($db->table('quarantine_entries')->whereIn('status', ['pending', 'partial'])->pluck('location_id'));
        $ids += $this->foreignIdsForOpenDocumentLines('location_id');
        $ids += $this->ids($db->table('import_files')
            ->whereIn('status', ['draft', 'in_transit', 'customs'])
            ->whereNotNull('receiving_location_id')
            ->pluck('receiving_location_id'));
        $ids += $this->ids($db->table('packages')
            ->join('import_files', 'import_files.id', '=', 'packages.import_file_id')
            ->whereIn('import_files.status', ['draft', 'in_transit', 'customs'])
            ->whereNotNull('packages.location_id')
            ->pluck('packages.location_id'));
        $ids += $this->ids($db->table('channel_listing_locations')->pluck('location_id'));

        return $ids;
    }

    /** @param array<int,true> $locationIds @return array<int,true> */
    private function requiredContactIds(array $locationIds): array
    {
        $db = DB::connection('period_source');
        $ids = $this->ids($db->table('contacts')->where('is_active', true)->pluck('id'));

        $balanceIds = $db->table('contact_transactions')
            ->select('contact_id')
            ->groupBy('contact_id')
            ->havingRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) <> 0")
            ->pluck('contact_id');
        $ids += $this->ids($balanceIds);

        $ids += $this->ids($db->table('documents')
            ->whereIn('document_type', ['sales_order', 'purchase_order'])
            ->where('status', 'confirmed')
            ->whereNotNull('contact_id')
            ->pluck('contact_id'));
        $ids += $this->ids($db->table('import_files')
            ->whereIn('status', ['draft', 'in_transit', 'customs'])
            ->pluck('supplier_contact_id'));
        $ids += $this->ids($db->table('securities')
            ->whereIn('status', ['portfolio', 'issued', 'endorsed', 'banked'])
            ->whereNotNull('contact_id')
            ->pluck('contact_id'));
        $ids += $this->ids($db->table('securities')
            ->whereIn('status', ['portfolio', 'issued', 'endorsed', 'banked'])
            ->whereNotNull('endorsed_to_contact_id')
            ->pluck('endorsed_to_contact_id'));

        if ($locationIds !== []) {
            $ids += $this->ids($db->table('locations')
                ->whereIn('id', array_keys($locationIds))
                ->whereNotNull('subcontractor_contact_id')
                ->pluck('subcontractor_contact_id'));
        }

        return $ids;
    }

    /** @return array<int,true> */
    private function requiredFinancialAccountIds(string $accountTable, string $movementTable, string $foreignKey): array
    {
        $db = DB::connection('period_source');
        $ids = $this->ids($db->table($accountTable)->where('is_active', true)->pluck('id'));

        $balanceIds = $db->table($movementTable)
            ->select($foreignKey)
            ->groupBy($foreignKey)
            ->havingRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) <> 0")
            ->pluck($foreignKey);

        return $ids + $this->ids($balanceIds);
    }

    /** @return array<int,true> */
    private function foreignIdsForOpenDocumentLines(string $column): array
    {
        return $this->ids(
            DB::connection('period_source')->table('document_lines')
                ->join('documents', 'documents.id', '=', 'document_lines.document_id')
                ->whereIn('documents.document_type', ['sales_order', 'purchase_order'])
                ->where('documents.status', 'confirmed')
                ->whereNotNull('document_lines.'.$column)
                ->pluck('document_lines.'.$column),
        );
    }

    /** @return array<int,true> */
    private function activeRecipeLineIds(): array
    {
        return $this->ids(
            DB::connection('period_source')->table('production_recipe_lines')
                ->join('production_recipes', 'production_recipes.id', '=', 'production_recipe_lines.production_recipe_id')
                ->where('production_recipes.is_active', true)
                ->pluck('production_recipe_lines.id'),
        );
    }

    /** @param array<int,true> $ids @return array<int,true> */
    private function withCategoryParents(array $ids): array
    {
        $db = DB::connection('period_source');

        do {
            $before = count($ids);
            $parents = $db->table('product_categories')
                ->whereIn('id', array_keys($ids))
                ->whereNotNull('parent_id')
                ->pluck('parent_id');
            $ids += $this->ids($parents);
        } while (count($ids) > $before);

        return $ids;
    }

    /** @param list<int> $ids */
    private function copyCategories(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $rows = DB::connection('period_source')->table('product_categories')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        $pending = array_fill_keys($ids, true);
        $ordered = [];

        while ($pending !== []) {
            $progress = false;

            foreach (array_keys($pending) as $id) {
                $row = $rows->get($id);

                if (! $row) {
                    throw new DomainException("Carry source product_categories #{$id} bulunamadı.");
                }

                if ($row->parent_id === null || ! isset($pending[(int) $row->parent_id])) {
                    $ordered[] = (int) $id;
                    unset($pending[$id]);
                    $progress = true;
                }
            }

            if (! $progress) {
                throw new DomainException('Product category parent zincirinde cycle tespit edildi.');
            }
        }

        return $this->copier->copyIds('product_categories', $ordered);
    }

    /** @param list<int> $contactIds @param list<int> $categoryIds */
    private function copyContactCategoryPivot(array $contactIds, array $categoryIds): int
    {
        if ($contactIds === [] || $categoryIds === []) {
            return 0;
        }

        $rows = DB::connection('period_source')->table('contact_category')
            ->whereIn('contact_id', $contactIds)
            ->whereIn('contact_category_id', $categoryIds)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();

        return $this->copier->copyPivot(
            'contact_category',
            $rows,
            ['contact_id', 'contact_category_id'],
        );
    }

    /** @param list<int> $ownerIds */
    private function copyDependentIds(string $table, string $foreignKey, array $ownerIds): int
    {
        if ($ownerIds === []) {
            return 0;
        }

        $ids = DB::connection('period_source')->table($table)
            ->whereIn($foreignKey, $ownerIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->copier->copyIds($table, $ids);
    }

    /** @param list<int> $unitIds */
    private function copyUnitConversions(array $unitIds): int
    {
        if ($unitIds === []) {
            return 0;
        }

        $ids = DB::connection('period_source')->table('unit_conversions')
            ->whereIn('from_unit_id', $unitIds)
            ->whereIn('to_unit_id', $unitIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->copier->copyIds('unit_conversions', $ids);
    }

    /** @param array<int,true> $productIds */
    private function copyProductRelationRows(string $table, array $productIds): int
    {
        if ($productIds === []) {
            return 0;
        }

        $ids = DB::connection('period_source')->table($table)
            ->whereIn('set_product_id', array_keys($productIds))
            ->whereIn('component_product_id', array_keys($productIds))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->copier->copyIds($table, $ids);
    }

    /** @param list<int> $priceListIds @param list<int> $productIds */
    private function copyPriceListItems(array $priceListIds, array $productIds): int
    {
        if ($priceListIds === [] || $productIds === []) {
            return 0;
        }

        $ids = DB::connection('period_source')->table('price_list_items')
            ->whereIn('price_list_id', $priceListIds)
            ->whereIn('product_id', $productIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->copier->copyIds('price_list_items', $ids);
    }

    /**
     * @param Collection<int,mixed> $values
     * @return array<int,true>
     */
    private function ids(Collection $values): array
    {
        $result = [];

        foreach ($values as $value) {
            if ($value !== null && (int) $value > 0) {
                $result[(int) $value] = true;
            }
        }

        return $result;
    }

    /**
     * @param array<int,true> $ownerIds
     * @return array<int,true>
     */
    private function foreignIds(
        string $table,
        string $foreignKey,
        array $ownerIds,
        string $ownerKey = 'id',
    ): array {
        if ($ownerIds === []) {
            return [];
        }

        return $this->ids(
            DB::connection('period_source')->table($table)
                ->whereIn($ownerKey, array_keys($ownerIds))
                ->whereNotNull($foreignKey)
                ->pluck($foreignKey),
        );
    }

    private function assertPeriods(Period $source, Period $target): void
    {
        if ((int) $source->company_id !== (int) $target->company_id
            || (int) $target->year !== (int) $source->year + 1) {
            throw new DomainException('Kart devri yalnız aynı şirketin ardışık dönemleri arasında yapılabilir.');
        }
    }
}
