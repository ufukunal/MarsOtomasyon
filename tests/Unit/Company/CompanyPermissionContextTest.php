<?php

use App\Support\Auth\CompanyPermissionContext;

it('applies company-specific business denies before allows and does not affect Master abilities', function (): void {
    try {
        CompanyPermissionContext::use([
            'allow' => ['stock.post', 'users.create', ' stock.view '],
            'deny' => ['stock.post', 'finance.approve'],
        ]);

        expect(CompanyPermissionContext::decision('stock.post'))->toBeFalse()
            ->and(CompanyPermissionContext::decision('finance.approve'))->toBeFalse()
            ->and(CompanyPermissionContext::decision('stock.view'))->toBeTrue()
            ->and(CompanyPermissionContext::decision('users.create'))->toBeNull()
            ->and(CompanyPermissionContext::decision('sales.create'))->toBeNull();
    } finally {
        CompanyPermissionContext::clear();
    }
});

it('clears company permission overrides on request boundary', function (): void {
    CompanyPermissionContext::use(['deny' => ['stock.post']]);
    CompanyPermissionContext::clear();

    expect(CompanyPermissionContext::decision('stock.post'))->toBeNull();
});
