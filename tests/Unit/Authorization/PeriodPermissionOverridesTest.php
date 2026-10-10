<?php

use App\Support\Auth\PeriodPermissionContext as Permissions;

afterEach(function (): void {
    Permissions::clear();
});

it('lets period-specific deny override allow and supports explicit allow', function (): void {
    Permissions::use(['allow' => ['stock.post', 'sales.confirm'], 'deny' => ['stock.post']]);
    expect(Permissions::decision('stock.post'))->toBeFalse()
        ->and(Permissions::decision('sales.confirm'))->toBeTrue()
        ->and(Permissions::decision('purchases.approve'))->toBeNull();
});

it('never lets period overrides escalate company and master permissions', function (): void {
    Permissions::use([
        'allow' => ['users.manage', 'companies.update', 'roles.assign', 'periods.delete', 'stock.post'],
        'deny' => ['audit.read', 'stock.post'],
    ]);
    foreach (['users.manage', 'companies.update', 'roles.assign', 'periods.delete', 'audit.read'] as $master) {
        expect(Permissions::decision($master))->toBeNull();
    }
    expect(Permissions::decision('stock.post'))->toBeFalse();
});

it('normalizes repeated and invalid entries and clears privileges between requests', function (): void {
    Permissions::use(['allow' => [' stock.post ', 'stock.post', '', 'unscoped']]);
    expect(Permissions::decision('stock.post'))->toBeTrue();
    Permissions::clear();
    expect(Permissions::decision('stock.post'))->toBeNull();
});
