<?php

namespace App\Support\Documents;

use App\Models\Period\Document;

/**
 * Navigation only. It never changes ledger entries or bypasses posting rules.
 * Do not add arbitrary route names from user-supplied document_type strings.
 */
final class DocumentEditNavigation
{
    /** @var array<string, array{route: string, permission: string}> */
    private const TYPES = [
        'quote' => ['route' => 'sales.quotes.edit', 'permission' => 'quotes.view'],
        'sales_order' => ['route' => 'sales.orders.edit', 'permission' => 'sales_orders.view'],
        'dispatch' => ['route' => 'sales.dispatches.edit', 'permission' => 'dispatches.view'],
        'sales_invoice' => ['route' => 'sales.invoices.edit', 'permission' => 'sales_invoices.view'],
        'purchase_quote' => ['route' => 'purchases.quotes.edit', 'permission' => 'purchase_quotes.view'],
        'purchase_order' => ['route' => 'purchases.orders.edit', 'permission' => 'purchase_orders.view'],
        'purchase_dispatch' => ['route' => 'purchases.dispatches.edit', 'permission' => 'purchase_dispatches.view'],
        'goods_receipt' => ['route' => 'purchases.receipts.edit', 'permission' => 'goods_receipts.view'],
        'supplier_invoice' => ['route' => 'purchases.invoices.edit', 'permission' => 'supplier_invoices.view'],
        'proforma' => ['route' => 'sales.proformas.show', 'permission' => 'proformas.view'],
        'collection' => ['route' => 'finance.collections.create', 'permission' => 'collections.view'],
        'payment' => ['route' => 'purchases.payments.create', 'permission' => 'payments.view'],
        'contact_debit_credit' => ['route' => 'finance.contact-debit-credit', 'permission' => 'contacts.update'],
        'expense' => ['route' => 'finance.operations', 'permission' => 'expenses.view'],
        'advance' => ['route' => 'finance.operations', 'permission' => 'advances.view'],
        'advance_return' => ['route' => 'finance.operations', 'permission' => 'advances.view'],
        'sales_return' => ['route' => 'returns.center', 'permission' => 'returns.view'],
        'purchase_return' => ['route' => 'returns.center', 'permission' => 'returns.view'],
        'transfer' => ['route' => 'stock.transfers.show', 'permission' => 'transfers.view'],
        'warehouse_slip' => ['route' => 'stock.warehouse-slips.show', 'permission' => 'warehouse_slips.view'],
        'stock_count' => ['route' => 'stock.counts.show', 'permission' => 'stock_counts.view'],
    ];

    /** @return array{route:string,permission:string}|null */
    public static function forType(string $type): ?array
    {
        return self::TYPES[$type] ?? null;
    }

    public static function url(string $type, ?int $id): ?string
    {
        $destination = self::forType($type);

        if ($destination === null || $id === null || $id <= 0
            || ! auth()->check() || ! auth()->user()->can($destination['permission'])) {
            return null;
        }

        // These modules use ?belge=ID rather than /{id}. Only pass the
        // selected record when it is a real Document of the expected type.
        // The shared catalog also checks reversals and mutation permissions;
        // a view-only user gets the list, never an unauthorized correction.
        if (in_array($type, [
            'sales_return', 'purchase_return', 'collection', 'payment',
            'contact_debit_credit', 'expense', 'advance', 'advance_return',
        ], true)) {
            $document = Document::query()
                ->with(['incomingRelations', 'outgoingRelations'])
                ->where('document_type', $type)
                ->find($id);

            if ($document === null) {
                return null;
            }

            $entry = DocumentEditCatalog::entries()[$type] ?? null;

            return $entry === null
                ? route($destination['route'])
                : DocumentEditCatalog::url($document, $entry);
        }

        return route($destination['route'], ['id' => $id]);
    }
}
