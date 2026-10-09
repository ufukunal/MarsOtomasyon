<?php

use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelExternalEventRegistryService;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2REG');
    $this->v2RegistryAccount = SalesChannelAccount::query()->create([
        'company_id' => $company->id,
        'name' => 'V2 Event Account', 'platform' => 'trendyol',
        'credentials_encrypted' => [], 'settings' => [], 'is_active' => true,
    ]);
});

it('v2 registry reserves a new channel order only once', function () {
    $registry = app(ChannelExternalEventRegistryService::class);
    $first = $registry->reserve($this->v2RegistryAccount, 'order', 'V2-EXTERNAL-ORDER-123');
    $again = $registry->reserve($this->v2RegistryAccount, 'order', 'V2-EXTERNAL-ORDER-123');
    expect($first->reserved)->toBeTrue()
        ->and($again->reserved)->toBeFalse()
        ->and($first->registryId)->toBe($again->registryId);
});

it('v2 registry refuses missing external order identity', function () {
    expect(fn () => app(ChannelExternalEventRegistryService::class)->reserve($this->v2RegistryAccount, 'order', '  '))
        ->toThrow(DomainException::class);
});

it('v2 registry refuses unknown external event type before persistence', function () {
    expect(fn () => app(ChannelExternalEventRegistryService::class)->reserve($this->v2RegistryAccount, 'payment', 'V2PAY'))
        ->toThrow(DomainException::class);
});

it('v2 failed external event reservation is claimable again without a duplicate key', function () {
    $registry = app(ChannelExternalEventRegistryService::class);
    $first = $registry->reserve($this->v2RegistryAccount, 'order', 'V2-RETRY');
    $registry->markFailed($first->registryId);
    $again = $registry->reserve($this->v2RegistryAccount, 'order', 'V2-RETRY');
    expect($again->reserved)->toBeTrue()
        ->and($again->registryId)->toBe($first->registryId);
});

it('v2 registry is idempotent when same provenance is marked done twice', function () {
    $registry = app(ChannelExternalEventRegistryService::class);
    $event = $registry->reserve($this->v2RegistryAccount, 'order', 'V2-DONE');
    $registry->markDone($event->registryId, 41, 123);
    $registry->markDone($event->registryId, 41, 123);
    $repeat = $registry->reserve($this->v2RegistryAccount, 'order', 'V2-DONE');
    expect($repeat->reserved)->toBeFalse()
        ->and($repeat->status)->toBe('done');
});

it('v2 registry refuses switching a completed event to a conflicting source document', function () {
    $registry = app(ChannelExternalEventRegistryService::class);
    $event = $registry->reserve($this->v2RegistryAccount, 'order', 'V2-PROVENANCE');
    $registry->markDone($event->registryId, 41, 123);
    expect(fn () => $registry->markDone($event->registryId, 41, 456))
        ->toThrow(DomainException::class);
});