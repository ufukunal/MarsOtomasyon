<?php

namespace App\Modules\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReportService
{
    /**
     * @param  array{as_of:string,currency:?string,warehouse_id:?int,account_type:?string}  $filters
     * @return array{
     *     finance:list<array{currency:string,treasury:float,receivable:float,payable:float,net:float}>,
     *     aging:list<array{id:int,code:string,name:string,type:string,currency:string,current:float,days_1_30:float,days_31_60:float,days_61_90:float,days_90_plus:float,total:float}>,
     *     stock:list<array{product_id:int,product_code:string,product_name:string,warehouse_id:int,warehouse_code:string,warehouse_name:string,quantity:float,unit_cost:float,value:float}>,
     *     movements:Collection<int, object>,
     *     warehouses:Collection<int, object>
     * }
     */
    public function build(int $companyId, array $filters): array
    {
        $asOf = CarbonImmutable::createFromFormat('Y-m-d', $filters['as_of'])->startOfDay();

        return [
            'finance' => $this->financeSnapshot($companyId, $filters, $asOf),
            'aging' => $this->aging($companyId, $filters, $asOf),
            'stock' => $this->stockValuation($companyId, $filters, $asOf),
            'movements' => $this->stockMovements($companyId, $filters, $asOf),
            'warehouses' => DB::table('warehouses')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ];
    }

    /**
     * @param  array{as_of:string,currency:?string,warehouse_id:?int,account_type:?string}  $filters
     * @return list<array{currency:string,treasury:float,receivable:float,payable:float,net:float}>
     */
    private function financeSnapshot(int $companyId, array $filters, CarbonImmutable $asOf): array
    {
        $accountQuery = DB::table('accounts as account')
            ->leftJoin('account_transactions as tx', function ($join) use ($asOf): void {
                $join->on('tx.company_id', '=', 'account.company_id')
                    ->on('tx.account_id', '=', 'account.id')
                    ->where('tx.posting_date', '<=', $asOf->toDateString());
            })
            ->where('account.company_id', $companyId)
            ->select('account.id', 'account.book_currency_code', 'account.type')
            ->selectRaw('COALESCE(SUM(tx.amount), 0) AS balance')
            ->groupBy('account.id', 'account.book_currency_code', 'account.type');

        if ($filters['currency'] !== null) {
            $accountQuery->where('account.book_currency_code', $filters['currency']);
        }
        if ($filters['account_type'] !== null) {
            $accountQuery->where('account.type', $filters['account_type']);
        }

        $accountRows = $accountQuery->get();

        $treasuryQuery = DB::table('treasury_movements')
            ->where('company_id', $companyId)
            ->where('posting_date', '<=', $asOf->toDateString())
            ->select('currency_code')
            ->selectRaw('COALESCE(SUM(signed_amount), 0) AS balance')
            ->groupBy('currency_code');

        if ($filters['currency'] !== null) {
            $treasuryQuery->where('currency_code', $filters['currency']);
        }

        $treasuryByCurrency = $treasuryQuery->get()->keyBy('currency_code');
        $currencies = $accountRows->pluck('book_currency_code')
            ->merge($treasuryByCurrency->keys())
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($filters['currency'] !== null && ! $currencies->contains($filters['currency'])) {
            $currencies->push($filters['currency']);
        }

        $rows = [];
        foreach ($currencies as $currency) {
            $currency = (string) $currency;
            $receivable = 0.0;
            $payable = 0.0;

            foreach ($accountRows->where('book_currency_code', $currency) as $account) {
                $balance = (float) $account->balance;
                if ($balance > 0) {
                    $receivable += $balance;
                } elseif ($balance < 0) {
                    $payable += abs($balance);
                }
            }

            $treasury = (float) ($treasuryByCurrency->get($currency)->balance ?? 0);
            $rows[] = [
                'currency' => $currency,
                'treasury' => round($treasury, 6),
                'receivable' => round($receivable, 6),
                'payable' => round($payable, 6),
                'net' => round($treasury + $receivable - $payable, 6),
            ];
        }

        return $rows;
    }

    /**
     * @param  array{as_of:string,currency:?string,warehouse_id:?int,account_type:?string}  $filters
     * @return list<array{id:int,code:string,name:string,type:string,currency:string,current:float,days_1_30:float,days_31_60:float,days_61_90:float,days_90_plus:float,total:float}>
     */
    private function aging(int $companyId, array $filters, CarbonImmutable $asOf): array
    {
        $accountsQuery = DB::table('accounts')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('legal_name');

        if ($filters['currency'] !== null) {
            $accountsQuery->where('book_currency_code', $filters['currency']);
        }
        if ($filters['account_type'] !== null) {
            $accountsQuery->where('type', $filters['account_type']);
        }

        $accounts = $accountsQuery->get([
            'id',
            'code',
            'legal_name',
            'type',
            'book_currency_code',
            'due_days',
        ]);

        if ($accounts->isEmpty()) {
            return [];
        }

        $transactions = DB::table('account_transactions')
            ->where('company_id', $companyId)
            ->whereIn('account_id', $accounts->pluck('id')->all())
            ->where('posting_date', '<=', $asOf->toDateString())
            ->orderBy('account_id')
            ->orderBy('posting_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'account_id', 'posting_date', 'amount'])
            ->groupBy('account_id');

        $rows = [];
        foreach ($accounts as $account) {
            $accountTransactions = $transactions->get($account->id, collect());
            $net = round((float) $accountTransactions->sum(fn (object $tx): float => (float) $tx->amount), 6);
            if (abs($net) < 0.000001) {
                continue;
            }

            $direction = $net > 0 ? 1 : -1;
            $lots = $this->outstandingLots(
                $accountTransactions,
                $direction,
                max(0, (int) $account->due_days),
            );

            $buckets = [
                'current' => 0.0,
                'days_1_30' => 0.0,
                'days_31_60' => 0.0,
                'days_61_90' => 0.0,
                'days_90_plus' => 0.0,
            ];

            foreach ($lots as $lot) {
                $dueDate = CarbonImmutable::createFromFormat('Y-m-d', $lot['due_date'])->startOfDay();
                $amount = $lot['amount'];

                if ($dueDate->greaterThanOrEqualTo($asOf)) {
                    $buckets['current'] += $amount;
                    continue;
                }

                $daysOverdue = $dueDate->diffInDays($asOf);
                if ($daysOverdue <= 30) {
                    $buckets['days_1_30'] += $amount;
                } elseif ($daysOverdue <= 60) {
                    $buckets['days_31_60'] += $amount;
                } elseif ($daysOverdue <= 90) {
                    $buckets['days_61_90'] += $amount;
                } else {
                    $buckets['days_90_plus'] += $amount;
                }
            }

            $rows[] = [
                'id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->legal_name,
                'type' => (string) $account->type,
                'currency' => (string) $account->book_currency_code,
                'current' => round($buckets['current'], 6),
                'days_1_30' => round($buckets['days_1_30'], 6),
                'days_31_60' => round($buckets['days_31_60'], 6),
                'days_61_90' => round($buckets['days_61_90'], 6),
                'days_90_plus' => round($buckets['days_90_plus'], 6),
                'total' => round(array_sum($buckets), 6),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, object>  $transactions
     * @return list<array{amount:float,due_date:string}>
     */
    private function outstandingLots(Collection $transactions, int $direction, int $dueDays): array
    {
        $lots = [];
        $credit = 0.0;

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount * $direction;

            if ($amount > 0.000001) {
                if ($credit > 0) {
                    $applied = min($credit, $amount);
                    $credit -= $applied;
                    $amount -= $applied;
                }

                if ($amount > 0.000001) {
                    $postingDate = CarbonImmutable::parse((string) $transaction->posting_date);
                    $lots[] = [
                        'amount' => $amount,
                        'due_date' => $postingDate->addDays($dueDays)->toDateString(),
                    ];
                }

                continue;
            }

            if ($amount >= -0.000001) {
                continue;
            }

            $payment = abs($amount);
            while ($payment > 0.000001 && $lots !== []) {
                $available = $lots[0]['amount'];
                $applied = min($available, $payment);
                $lots[0]['amount'] -= $applied;
                $payment -= $applied;

                if ($lots[0]['amount'] <= 0.000001) {
                    array_shift($lots);
                }
            }

            if ($payment > 0.000001) {
                $credit += $payment;
            }
        }

        return array_values($lots);
    }

    /**
     * @param  array{as_of:string,currency:?string,warehouse_id:?int,account_type:?string}  $filters
     * @return list<array{product_id:int,product_code:string,product_name:string,warehouse_id:int,warehouse_code:string,warehouse_name:string,quantity:float,unit_cost:float,value:float}>
     */
    private function stockValuation(int $companyId, array $filters, CarbonImmutable $asOf): array
    {
        $ranked = DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->whereDate('occurred_at', '<=', $asOf->toDateString())
            ->when(
                $filters['warehouse_id'] !== null,
                fn ($query) => $query->where('warehouse_id', $filters['warehouse_id']),
            )
            ->select([
                'product_id',
                'warehouse_id',
                'location_id',
                'balance_quantity_after',
                'average_unit_cost_after',
                'inventory_value_after',
            ])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY product_id, warehouse_id, location_id ORDER BY occurred_at DESC, id DESC) AS row_number');

        $snapshot = DB::query()
            ->fromSub($ranked, 'snapshot')
            ->join('products as product', 'product.id', '=', 'snapshot.product_id')
            ->join('warehouses as warehouse', 'warehouse.id', '=', 'snapshot.warehouse_id')
            ->where('snapshot.row_number', 1)
            ->where('product.company_id', $companyId)
            ->where('warehouse.company_id', $companyId)
            ->groupBy(
                'snapshot.product_id',
                'product.code',
                'product.name',
                'snapshot.warehouse_id',
                'warehouse.code',
                'warehouse.name',
            )
            ->select([
                'snapshot.product_id',
                'product.code as product_code',
                'product.name as product_name',
                'snapshot.warehouse_id',
                'warehouse.code as warehouse_code',
                'warehouse.name as warehouse_name',
            ])
            ->selectRaw('COALESCE(SUM(snapshot.balance_quantity_after), 0) AS quantity')
            ->selectRaw('CASE WHEN COALESCE(SUM(snapshot.balance_quantity_after), 0) = 0 THEN 0 ELSE COALESCE(SUM(snapshot.inventory_value_after), 0) / SUM(snapshot.balance_quantity_after) END AS unit_cost')
            ->selectRaw('COALESCE(SUM(snapshot.inventory_value_after), 0) AS value')
            ->orderBy('product.code')
            ->orderBy('warehouse.code')
            ->get();

        return $snapshot->map(static fn (object $row): array => [
            'product_id' => (int) $row->product_id,
            'product_code' => (string) $row->product_code,
            'product_name' => (string) $row->product_name,
            'warehouse_id' => (int) $row->warehouse_id,
            'warehouse_code' => (string) $row->warehouse_code,
            'warehouse_name' => (string) $row->warehouse_name,
            'quantity' => round((float) $row->quantity, 6),
            'unit_cost' => round((float) $row->unit_cost, 6),
            'value' => round((float) $row->value, 6),
        ])->all();
    }

    /**
     * @param  array{as_of:string,currency:?string,warehouse_id:?int,account_type:?string}  $filters
     * @return Collection<int, object>
     */
    private function stockMovements(int $companyId, array $filters, CarbonImmutable $asOf): Collection
    {
        return DB::table('stock_movements as movement')
            ->join('products as product', function ($join): void {
                $join->on('product.company_id', '=', 'movement.company_id')
                    ->on('product.id', '=', 'movement.product_id');
            })
            ->join('warehouses as warehouse', function ($join): void {
                $join->on('warehouse.company_id', '=', 'movement.company_id')
                    ->on('warehouse.id', '=', 'movement.warehouse_id');
            })
            ->where('movement.company_id', $companyId)
            ->whereDate('movement.occurred_at', '<=', $asOf->toDateString())
            ->when(
                $filters['warehouse_id'] !== null,
                fn ($query) => $query->where('movement.warehouse_id', $filters['warehouse_id']),
            )
            ->select([
                'movement.id',
                'movement.occurred_at',
                'movement.movement_type',
                'movement.quantity_delta',
                'movement.unit_cost',
                'movement.value_delta',
                'movement.source_type',
                'movement.source_id',
                'product.code as product_code',
                'product.name as product_name',
                'warehouse.code as warehouse_code',
                'warehouse.name as warehouse_name',
            ])
            ->orderByDesc('movement.occurred_at')
            ->orderByDesc('movement.id')
            ->limit(100)
            ->get();
    }
}
