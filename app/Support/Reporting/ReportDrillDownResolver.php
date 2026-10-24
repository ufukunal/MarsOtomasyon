<?php

namespace App\Support\Reporting;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ReportDrillDownResolver
{
    /**
     * @param  array<string,mixed>  $row
     * @return array{label:string,url:string}|null
     */
    public function resolve(
        ReportDrillDownDefinition $definition,
        array $row,
        User $actor,
    ): ?array {
        if (! Gate::forUser($actor)->allows($definition->permission)) {
            return null;
        }

        $id = $row[$definition->idColumn] ?? null;

        if (! is_numeric((string) $id) || (int) $id < 1) {
            return null;
        }

        $route = match ($definition->target) {
            'products' => ['products.edit', 'product'],
            'contacts' => ['contacts.edit', 'contact'],
            'sales_quotes' => ['sales.quotes.edit', 'id'],
            'sales_orders' => ['sales.orders.edit', 'id'],
            'sales_dispatches' => ['sales.dispatches.edit', 'id'],
            'sales_proformas' => ['sales.proformas.show', 'id'],
            'purchase_quotes' => ['purchases.quotes.edit', 'id'],
            'purchase_orders' => ['purchases.orders.edit', 'id'],
            'purchase_dispatches' => ['purchases.dispatches.edit', 'id'],
            'goods_receipts' => ['purchases.receipts.edit', 'id'],
            'stock_transfers' => ['stock.transfers.show', 'id'],
            'stock_counts' => ['stock.counts.show', 'id'],
            'warehouse_slips' => ['stock.warehouse-slips.show', 'id'],
            'sales_invoices' => ['sales.invoices.edit', 'id'],
            'supplier_invoices' => ['purchases.invoices.edit', 'id'],
            default => null,
        };

        if ($route === null) {
            return null;
        }

        return [
            'label' => $definition->label,
            'url' => route($route[0], [$route[1] => (int) $id]),
        ];
    }
}
