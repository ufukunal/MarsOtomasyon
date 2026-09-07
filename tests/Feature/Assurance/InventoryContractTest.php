<?php

declare(strict_types=1);

use App\Foundation\Assurance\AssuranceInventory;

it('builds a machine-readable full surface inventory with authorization metadata', function (): void {
    $inventory = app(AssuranceInventory::class)->build();

    expect($inventory['schema_version'])->toBe(1)
        ->and($inventory['surfaces']['http'])->not->toBeEmpty()
        ->and($inventory['surfaces']['cli'])->not->toBeEmpty()
        ->and($inventory['surfaces']['data'])->not->toBeEmpty()
        ->and($inventory['coverage_map'])->not->toBeEmpty()
        ->and($inventory['route_authorization_map'])->not->toBeEmpty();

    $finalize = collect($inventory['surfaces']['http'])
        ->firstWhere('name', 'sales-invoices.finalize');

    expect($finalize)->toBeArray()
        ->and($finalize['mutates_state'])->toBeTrue()
        ->and($finalize['financial_effect'])->toBeTrue()
        ->and($finalize['tenant_scoped'])->toBeTrue()
        ->and($finalize['requires_auth'])->toBeTrue()
        ->and($finalize['required_permission'])->toBe('can:sales_invoices.manage')
        ->and($finalize['trust_boundary'])->toBe('session-auth')
        ->and($finalize['risk_level'])->toBe('critical')
        ->and($finalize['integration_test'])->toBeTrue();
});

it('models non-session mutation trust boundaries explicitly instead of pretending they are authenticated', function (): void {
    $routes = collect(app(AssuranceInventory::class)->build()['surfaces']['http']);

    $webhook = $routes->firstWhere('name', 'channels.webhook');
    expect($webhook)->toBeArray()
        ->and($webhook['mutates_state'])->toBeTrue()
        ->and($webhook['requires_auth'])->toBeFalse()
        ->and($webhook['trust_boundary'])->toBe('external-webhook');

    $scannerEnrollment = $routes->first(
        static fn (array $route): bool => $route['uri'] === 'api/v1/scanner/enroll' || $route['uri'] === 'scanner/enroll',
    );
    expect($scannerEnrollment)->toBeArray()
        ->and($scannerEnrollment['requires_auth'])->toBeFalse()
        ->and($scannerEnrollment['trust_boundary'])->toBe('scanner-enrollment');
});

it('does not leave a mutating route with an unclassified trust boundary', function (): void {
    $routes = collect(app(AssuranceInventory::class)->build()['surfaces']['http']);

    $missing = $routes
        ->filter(static fn (array $route): bool => $route['mutates_state'] && $route['trust_boundary'] === 'missing')
        ->map(static fn (array $route): string => implode(',', $route['methods']).' '.$route['uri'])
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
