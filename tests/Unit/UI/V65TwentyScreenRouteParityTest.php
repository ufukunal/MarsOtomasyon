<?php

it('exposes all twenty prototype operation routes inside the Laravel application', function (): void {
    $routes = file_get_contents(base_path('routes/web.php'));

    foreach ([
        'sales.quotes.index',
        'sales.orders.index',
        'sales.dispatches.index',
        'sales.invoices.index',
        'purchases.quotes.index',
        'purchases.orders.index',
        'purchases.dispatches.index',
        'purchases.receipts.index',
        'purchases.invoices.index',
        'finance.collections.create',
        'purchases.payments.create',
        'finance.contact-transfers',
        'finance.checks.index',
        'finance.checks.incoming',
        'finance.checks.outgoing',
        'finance.checks.endorsement',
        'finance.notes.index',
        'finance.notes.incoming',
        'finance.notes.outgoing',
        'finance.notes.endorsement',
    ] as $name) {
        expect($routes)->toContain("->name('".$name."')");
    }
});
