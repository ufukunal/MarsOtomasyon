<?php

it('redirects unauthenticated users away from every ordinary business module', function (string $routeName): void {
    auth()->logout();
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'home',
    'locations.index', 'units.index', 'categories.index', 'brands.index', 'contacts.index',
    'products.index', 'price-lists.index', 'company-copy.index',
    'sales.quotes.index', 'sales.orders.index', 'sales.dispatches.index',
    'sales.invoices.index', 'sales.proformas.index', 'sales.vehicle-hot-sale',
    'purchases.orders.index', 'purchases.receipts.index', 'purchases.invoices.index',
    'purchases.payments.create', 'purchases.supplier-performance',
    'finance.collections.create', 'finance.contact-debit-credit', 'finance.contact-aging',
    'finance.accounts', 'finance.operations', 'finance.bank-statements', 'finance.securities',
    'returns.center', 'imports.shipments', 'imports.index',
    'production.recipes', 'production.orders', 'production.subcontracting',
    'channels.accounts', 'channels.listings', 'channels.sync',
    'reports.center', 'reports.consolidated', 'reports.exports',
    'reports.templates', 'reports.print-history',
    'stock.status', 'stock.movements', 'stock.counts.index',
    'stock.quarantine.index', 'stock.reservations.index', 'stock.transfers.index',
    'stock.warehouse-slips.index', 'settings.periods', 'settings.period-carry',
    'settings.integrity', 'settings.operations',
]);

it('forbids unsigned channel asset URLs before resolving attachments', function (): void {
    $this->get('/channel-assets/1/2/3/4')->assertForbidden();
});
