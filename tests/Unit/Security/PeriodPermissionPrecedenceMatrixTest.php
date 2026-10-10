<?php

use App\Support\Auth\PeriodPermissionContext;

afterEach(function (): void {
    PeriodPermissionContext::clear();
});

it('applies explicit period deny before an overlapping allow and never elevates master privileges', function (): void {
    PeriodPermissionContext::use([
        'allow' => ['sales_orders.create', 'stock.view', 'roles.update', 'reports.view'],
        'deny' => ['stock.view', 'users.update', 'companies.delete'],
    ]);

    expect(PeriodPermissionContext::decision('sales_orders.create'))->toBeTrue()
        ->and(PeriodPermissionContext::decision('stock.view'))->toBeFalse()
        ->and(PeriodPermissionContext::decision('reports.view'))->toBeTrue()
        ->and(PeriodPermissionContext::decision('roles.update'))->toBeNull()
        ->and(PeriodPermissionContext::decision('companies.delete'))->toBeNull()
        ->and(PeriodPermissionContext::decision('users.update'))->toBeNull()
        ->and(PeriodPermissionContext::decision('stock.delete'))->toBeNull();
});

it('normalizes empty and repeated override rules without leaving stale access across periods', function (): void {
    PeriodPermissionContext::use([
        'allow' => ['stock.create', ' stock.create ', '', 'brokenrule', 'print_profiles.view'],
        'deny' => ['stock.delete', 'stock.delete'],
    ]);

    expect(PeriodPermissionContext::decision('stock.create'))->toBeTrue()
        ->and(PeriodPermissionContext::decision('stock.delete'))->toBeFalse()
        ->and(PeriodPermissionContext::decision('print_profiles.view'))->toBeNull();

    PeriodPermissionContext::clear();

    expect(PeriodPermissionContext::decision('stock.create'))->toBeNull()
        ->and(PeriodPermissionContext::decision('stock.delete'))->toBeNull();
});

it('ignores malformed permission override collections rather than granting all period abilities', function (): void {
    PeriodPermissionContext::use(['allow' => 'all', 'deny' => 'none']);

    expect(PeriodPermissionContext::decision('sales_invoices.post'))->toBeNull()
        ->and(PeriodPermissionContext::decision('purchases.approve'))->toBeNull();
});
