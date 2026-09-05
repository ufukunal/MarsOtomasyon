<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Shipping\AmbiguousShippingOutcome;
use App\Modules\Dispatches\Shipping\ShippingProviderRegistry;
use App\Modules\Dispatches\Shipping\ShippingService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fixtures\Dispatches\M28FakeShippingProvider;

uses(DatabaseMigrations::class);

it('creates one external shipment mapping and rejects create payload drift', function (): void {
    [$company, $dispatch] = m28ShippingFixture('M28-ONE');
    $provider = new M28FakeShippingProvider;
    $service = m28ShippingService($provider);
    $service->configureConnection((int) $company->getKey(), 'fixture', 'Fixture Shipping', ['token' => 'top-secret']);
    $key = (string) Str::uuid();

    $first = $service->createShipment((int) $company->getKey(), (int) $dispatch->getKey(), 'fixture', $key, [
        'package' => ['weight' => 2, 'width' => 10],
    ]);
    $replay = $service->createShipment((int) $company->getKey(), (int) $dispatch->getKey(), 'fixture', $key, [
        'package' => ['width' => 10, 'weight' => 2],
    ]);

    expect($first['external_shipment_id'])->toBe('EXT-1')
        ->and($replay['id'])->toBe($first['id'])
        ->and($provider->createCalls)->toBe(1)
        ->and(DB::table('external_shipment_mappings')->count())->toBe(1);

    $connection = DB::table('shipping_connections')->first();
    expect($connection)->not->toBeNull()
        ->and((string) $connection->credentials_encrypted)->not->toContain('top-secret');

    expect(fn () => $service->createShipment(
        (int) $company->getKey(),
        (int) $dispatch->getKey(),
        'fixture',
        $key,
        ['package' => ['weight' => 3, 'width' => 10]],
    ))->toThrow(DomainException::class, 'payload drift');
});

it('queries before retry after an ambiguous provider create and records tracking evidence once', function (): void {
    [$company, $dispatch] = m28ShippingFixture('M28-AMB');
    $provider = new M28FakeShippingProvider;
    $provider->ambiguousNextCreate = true;
    $service = m28ShippingService($provider);
    $service->configureConnection((int) $company->getKey(), 'fixture', 'Fixture Shipping', ['token' => 'secret']);
    $key = (string) Str::uuid();

    expect(fn () => $service->createShipment((int) $company->getKey(), (int) $dispatch->getKey(), 'fixture', $key))
        ->toThrow(AmbiguousShippingOutcome::class);

    expect((string) DB::table('shipping_provider_attempts')->where('operation', 'create')->value('status'))->toBe('ambiguous')
        ->and($provider->createCalls)->toBe(1);

    $provider->recover = ['external_id' => 'EXT-RECOVERED', 'tracking_number' => 'TRK-R', 'label_reference' => null, 'status' => 'created'];
    $mapping = $service->createShipment((int) $company->getKey(), (int) $dispatch->getKey(), 'fixture', $key);

    expect($mapping['external_shipment_id'])->toBe('EXT-RECOVERED')
        ->and($provider->findCalls)->toBe(1)
        ->and($provider->createCalls)->toBe(1);

    $firstTracking = $service->refreshTracking((int) $company->getKey(), (int) $dispatch->getKey(), 'fixture');
    $secondTracking = $service->refreshTracking((int) $company->getKey(), (int) $dispatch->getKey(), 'fixture');

    expect($firstTracking['status'])->toBe('in_transit')
        ->and($secondTracking)->toBe($firstTracking)
        ->and(DB::table('shipping_tracking_evidence')->count())->toBe(1);

    $cancelKey = (string) Str::uuid();
    $service->cancelShipment((int) $company->getKey(), (int) $dispatch->getKey(), 'fixture', $cancelKey);
    $service->cancelShipment((int) $company->getKey(), (int) $dispatch->getKey(), 'fixture', $cancelKey);

    expect($provider->cancelCalls)->toBe(1)
        ->and((string) DB::table('external_shipment_mappings')->value('status'))->toBe('cancelled');
});

/** @return array{Company, Dispatch} */
function m28ShippingFixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'CUST',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Customer '.$code,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $dispatch = Dispatch::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $account->getKey(),
        'sales_order_id' => null,
        'source_address_id' => null,
        'number' => 'DSP-'.$code,
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => 'draft',
        'dispatch_date' => '2026-09-02',
        'recipient_name' => 'Warehouse Receiver',
        'address_line1' => 'Mars Cad. 28',
        'address_line2' => null,
        'district' => 'Şişli',
        'city' => 'İstanbul',
        'postal_code' => '34360',
        'country_code' => 'TR',
        'carrier_name' => null,
        'carrier_service' => 'standard',
        'tracking_number' => null,
        'note' => null,
    ]);

    return [$company, $dispatch];
}

function m28ShippingService(M28FakeShippingProvider $provider): ShippingService
{
    $registry = new ShippingProviderRegistry;
    $registry->register($provider);

    return new ShippingService($registry);
}
