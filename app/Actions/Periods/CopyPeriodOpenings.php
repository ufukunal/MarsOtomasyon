<?php

namespace App\Actions\Periods;

use App\Models\Period;
use App\Support\Audit\AuditContext;
use App\Support\Period\PeriodContext;
use App\Support\Period\SourcePeriodContext;
use App\Support\PeriodCarry\PeriodCarryTableCopier;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CopyPeriodOpenings
{
    public function __construct(private readonly PeriodCarryTableCopier $copier) {}

    /** @return array<string,int> */
    public function handle(Period $source, Period $target): array
    {
        $this->assertPeriods($source, $target);
        PeriodContext::ensureWritable();

        if ((int) PeriodContext::periodId() !== (int) $target->id) {
            throw new DomainException('CopyPeriodOpenings hedef period context içinde çalışmalıdır.');
        }

        SourcePeriodContext::usePeriod($source);

        try {
            return DB::connection('period')->transaction(function () use ($source, $target): array {
                $counts = [];
                $counts['stock_balances'] = $this->copyStockBalances();
                $counts['product_costs'] = $this->copyProductCosts();
                $counts['stock_openings'] = $this->createStockOpenings($target);
                $counts['contact_openings'] = $this->createContactOpenings($target);
                $counts['cash_openings'] = $this->createCashOpenings($target);
                $counts['bank_openings'] = $this->createBankOpenings($target);
                $counts['securities'] = $this->copyUnmaturedSecurities($source);
                $counts['quarantine'] = $this->copyOpenQuarantine();
                $recipeCounts = $this->copyActiveRecipes();
                $counts['production_recipes'] = $recipeCounts['recipes'];
                $counts['production_recipe_lines'] = $recipeCounts['lines'];

                AuditContext::period(
                    'Dönem açılış snapshotları taşındı.',
                    [
                        'source_period_id' => (int) $source->id,
                        'target_period_id' => (int) $target->id,
                        ...$counts,
                    ],
                    null,
                    'period_openings_carried',
                );

                return $counts;
            }, attempts: 3);
        } finally {
            SourcePeriodContext::clear();
        }
    }

    private function copyStockBalances(): int
    {
        $targetProductIds = DB::connection('period')->table('products')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $targetLocationIds = DB::connection('period')->table('locations')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($targetProductIds === [] || $targetLocationIds === []) {
            return 0;
        }

        $rows = DB::connection('period_source')->table('stock_balances')
            ->whereIn('product_id', $targetProductIds)
            ->whereIn('location_id', $targetLocationIds)
            ->orderBy('id')
            ->get();
        $count = 0;

        foreach ($rows as $row) {
            $values = (array) $row;
            $values['reserved'] = bcadd(
                (string) (DB::connection('period')->table('stock_reservations')
                    ->where('product_id', $row->product_id)
                    ->where('location_id', $row->location_id)
                    ->where('status', 'active')
                    ->sum('quantity')),
                '0',
                3,
            );

            $existing = DB::connection('period')->table('stock_balances')->where('id', $row->id)->first();

            if ($existing) {
                if ((int) $existing->product_id !== (int) $row->product_id
                    || (int) $existing->location_id !== (int) $row->location_id) {
                    throw new DomainException("Target stock_balance #{$row->id} source kimliğiyle uyuşmuyor.");
                }

                unset($values['id']);
                DB::connection('period')->table('stock_balances')->where('id', $row->id)->update($values);
            } else {
                DB::connection('period')->table('stock_balances')->insert($values);
            }

            $count++;
        }

        $this->copier->resetSequence('stock_balances');

        return $count;
    }

    private function copyProductCosts(): int
    {
        $productIds = DB::connection('period')->table('products')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($productIds === []) {
            return 0;
        }

        $ids = DB::connection('period_source')->table('product_costs')
            ->whereIn('product_id', $productIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->copier->copyIds('product_costs', $ids);
    }

    private function createStockOpenings(Period $target): int
    {
        $existing = DB::connection('period')->table('stock_movements')
            ->where('reason', 'opening')
            ->where('movement_date', $target->starts_on->toDateString())
            ->count();

        if ($existing > 0) {
            return $existing;
        }

        $costs = DB::connection('period_source')->table('product_costs')
            ->pluck('moving_average', 'product_id');
        $actor = auth()->user();
        $count = 0;

        $balances = DB::connection('period')->table('stock_balances')
            ->where('quantity', '<>', 0)
            ->orderBy('id')
            ->get();

        foreach ($balances as $balance) {
            $quantity = bcadd((string) $balance->quantity, '0', 3);
            $absolute = str_starts_with($quantity, '-') ? substr($quantity, 1) : $quantity;
            $unitCost = bcadd((string) ($costs[(int) $balance->product_id] ?? '0'), '0', 4);
            $totalCost = bcadd(bcmul($absolute, $unitCost, 4), '0', 4);
            $productCode = (string) DB::connection('period')
                ->table('products')
                ->where('id', $balance->product_id)
                ->value('code');

            DB::connection('period')->table('stock_movements')->insert([
                'product_id' => (int) $balance->product_id,
                'location_id' => (int) $balance->location_id,
                'product_code' => $productCode,
                'movement_date' => $target->starts_on->toDateString(),
                'direction' => str_starts_with($quantity, '-') ? 'out' : 'in',
                'reason' => 'opening',
                'quantity' => $absolute,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'balance_after' => $quantity,
                'avg_cost_after' => $unitCost,
                'document_type' => null,
                'document_id' => null,
                'document_no' => null,
                'note' => 'Önceki dönem kapanış stok açılışı.',
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }

    private function createContactOpenings(Period $target): int
    {
        if (DB::connection('period')->table('contact_transactions')->where('transaction_type', 'opening')->exists()) {
            return DB::connection('period')->table('contact_transactions')->where('transaction_type', 'opening')->count();
        }

        $targetContactIds = DB::connection('period')->table('contacts')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($targetContactIds === []) {
            return 0;
        }

        $rows = DB::connection('period_source')->table('contact_transactions')
            ->selectRaw(
                "contact_id, currency, SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) AS balance"
            )
            ->whereIn('contact_id', $targetContactIds)
            ->groupBy('contact_id', 'currency')
            ->get();
        $actor = auth()->user();
        $count = 0;

        foreach ($rows as $row) {
            $balance = bcadd((string) $row->balance, '0', 4);

            if (bccomp($balance, '0', 4) === 0) {
                continue;
            }

            $negative = str_starts_with($balance, '-');

            DB::connection('period')->table('contact_transactions')->insert([
                'contact_id' => (int) $row->contact_id,
                'document_id' => null,
                'transaction_type' => 'opening',
                'direction' => $negative ? 'credit' : 'debit',
                'transaction_date' => $target->starts_on->toDateString(),
                'due_date' => null,
                'amount' => $negative ? substr($balance, 1) : $balance,
                'currency' => (string) $row->currency,
                'reversal_of_id' => null,
                'description' => 'Önceki dönem cari açılışı.',
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }

    private function createCashOpenings(Period $target): int
    {
        return $this->createFinancialOpenings(
            target: $target,
            accountTable: 'cash_accounts',
            movementTable: 'cash_movements',
            foreignKey: 'cash_account_id',
            extra: [],
        );
    }

    private function createBankOpenings(Period $target): int
    {
        return $this->createFinancialOpenings(
            target: $target,
            accountTable: 'bank_accounts',
            movementTable: 'bank_movements',
            foreignKey: 'bank_account_id',
            extra: ['origin' => 'book'],
        );
    }

    /** @param array<string,mixed> $extra */
    private function createFinancialOpenings(
        Period $target,
        string $accountTable,
        string $movementTable,
        string $foreignKey,
        array $extra,
    ): int {
        if (DB::connection('period')->table($movementTable)->where('movement_type', 'opening')->exists()) {
            return DB::connection('period')->table($movementTable)->where('movement_type', 'opening')->count();
        }

        $accountIds = DB::connection('period')->table($accountTable)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($accountIds === []) {
            return 0;
        }

        $rows = DB::connection('period_source')->table($movementTable)
            ->selectRaw(
                $foreignKey.", SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) AS balance"
            )
            ->whereIn($foreignKey, $accountIds)
            ->groupBy($foreignKey)
            ->get();
        $actor = auth()->user();
        $count = 0;

        foreach ($rows as $row) {
            $balance = bcadd((string) $row->balance, '0', 4);

            if (bccomp($balance, '0', 4) === 0) {
                continue;
            }

            $negative = str_starts_with($balance, '-');
            $values = [
                $foreignKey => (int) $row->{$foreignKey},
                'document_id' => null,
                'contact_id' => null,
                'movement_date' => $target->starts_on->toDateString(),
                'direction' => $negative ? 'out' : 'in',
                'amount' => $negative ? substr($balance, 1) : $balance,
                'description' => 'Önceki dönem açılışı.',
                'movement_type' => 'opening',
                'group_key' => null,
                'reversal_of_id' => null,
                'metadata' => json_encode(['carry_opening' => true], JSON_THROW_ON_ERROR),
                'created_by' => $actor?->id,
                'created_by_name' => $actor?->name,
                'created_at' => now(),
                'updated_at' => now(),
                ...$extra,
            ];

            if ($movementTable === 'cash_movements') {
                $values['reference'] = null;
            } else {
                $values['reference'] = null;
                $values['statement_fingerprint'] = null;
                $values['statement_value_date'] = null;
                $values['statement_description'] = null;
                $values['statement_balance'] = null;
                $values['reconciled_movement_id'] = null;
                $values['reconciled_at'] = null;
                $values['reconciled_by'] = null;
                $values['reconciled_by_name'] = null;
                $values['imported_at'] = null;
            }

            DB::connection('period')->table($movementTable)->insert($values);
            $count++;
        }

        return $count;
    }

    private function copyUnmaturedSecurities(Period $source): int
    {
        $rows = DB::connection('period_source')->table('securities')
            ->whereIn('status', ['portfolio', 'issued', 'endorsed', 'banked'])
            ->where('due_date', '>', $source->ends_on->toDateString())
            ->orderBy('id')
            ->get();
        $count = 0;

        foreach ($rows as $row) {
            $values = (array) $row;
            $values['contact_transaction_id'] = null;
            $values['last_payroll_id'] = null;

            if (DB::connection('period')->table('securities')->where('id', $row->id)->exists()) {
                unset($values['id']);
                DB::connection('period')->table('securities')->where('id', $row->id)->update($values);
            } else {
                DB::connection('period')->table('securities')->insert($values);
            }

            $count++;
        }

        $this->copier->resetSequence('securities');

        return $count;
    }

    private function copyOpenQuarantine(): int
    {
        $rows = DB::connection('period_source')->table('quarantine_entries')
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('id')
            ->get();
        $count = 0;

        foreach ($rows as $row) {
            $open = bcsub(
                bcsub((string) $row->quantity, (string) $row->released_quantity, 3),
                (string) $row->scrapped_quantity,
                3,
            );

            if (bccomp($open, '0', 3) <= 0) {
                continue;
            }

            $values = [
                'id' => (int) $row->id,
                'product_id' => (int) $row->product_id,
                'location_id' => (int) $row->location_id,
                'source_document_type' => null,
                'source_document_id' => null,
                'source_line_id' => null,
                'quantity' => $open,
                'released_quantity' => '0.000',
                'scrapped_quantity' => '0.000',
                'unit_cost' => (string) $row->unit_cost,
                'status' => 'pending',
                'decision_note' => null,
                'version' => 1,
                'created_by' => $row->created_by,
                'created_by_name' => $row->created_by_name,
                'decided_by' => null,
                'decided_by_name' => null,
                'decided_at' => null,
                'created_at' => $row->created_at,
                'updated_at' => now(),
            ];

            if (DB::connection('period')->table('quarantine_entries')->where('id', $row->id)->exists()) {
                $update = $values;
                unset($update['id']);
                DB::connection('period')->table('quarantine_entries')->where('id', $row->id)->update($update);
            } else {
                DB::connection('period')->table('quarantine_entries')->insert($values);
            }

            $count++;
        }

        $this->copier->resetSequence('quarantine_entries');

        return $count;
    }

    /** @return array{recipes:int,lines:int} */
    private function copyActiveRecipes(): array
    {
        $recipeIds = DB::connection('period_source')->table('production_recipes')
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $recipes = $this->copier->copyIds('production_recipes', $recipeIds);

        if ($recipeIds === []) {
            return ['recipes' => 0, 'lines' => 0];
        }

        $lineIds = DB::connection('period_source')->table('production_recipe_lines')
            ->whereIn('production_recipe_id', $recipeIds)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return [
            'recipes' => $recipes,
            'lines' => $this->copier->copyIds('production_recipe_lines', $lineIds),
        ];
    }

    private function assertPeriods(Period $source, Period $target): void
    {
        if ((int) $source->company_id !== (int) $target->company_id
            || (int) $target->year !== (int) $source->year + 1) {
            throw new DomainException('Opening carry yalnız aynı şirketin ardışık dönemleri arasında yapılabilir.');
        }
    }
}
