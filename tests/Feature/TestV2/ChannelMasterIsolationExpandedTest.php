<?php

use App\Models\ChannelExternalEventRegistry;
use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelExternalEventRegistryService;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2CHMASTER');
    $this->v2ChannelOwner = $company;
    $this->v2ChannelPeriod = $period;
});

function v2MasterChannelAccount(int $companyId, string $externalId = 'v2-store'): SalesChannelAccount
{
    return SalesChannelAccount::query()->create([
        'company_id' => $companyId,
        'platform' => 'trendyol',
        'name' => 'V2 Master Company Channel',
        'external_store_id' => $externalId,
        'credentials_encrypted' => ['api_key' => 'v2-secret-no-log', 'api_secret' => 'v2-long-secret'],
        'settings' => ['environment' => 'stage'],
        'is_active' => true,
    ]);
}

it('v2 master channel model omits encrypted credentials from serialized output', function () {
    $account = v2MasterChannelAccount($this->v2ChannelOwner->id);
    $array = $account->fresh()->toArray();
    expect($array)->not->toHaveKey('credentials_encrypted')
        ->and(json_encode($array))->not->toContain('v2-secret-no-log')
        ->and($account->fresh()->credentials()['api_key'])->toBe('v2-secret-no-log');
});

it('v2 marketplace account company identity cannot be reassigned with a model update', function () {
    [$other, $period] = $this->createCompanyWithPeriod('V2CHOTHER');
    $account = v2MasterChannelAccount($this->v2ChannelOwner->id);
    expect(fn () => $account->update(['company_id' => $other->id]))
        ->toThrow(LogicException::class);
    expect($account->fresh()->company_id)->toBe($this->v2ChannelOwner->id);
});

it('v2 marketplace account platform identity cannot be reassigned after creation', function () {
    $account = v2MasterChannelAccount($this->v2ChannelOwner->id);
    expect(fn () => $account->update(['platform' => 'hepsiburada']))
        ->toThrow(LogicException::class);
    expect($account->fresh()->platform->value)->toBe('trendyol');
});

it('v2 marketplace account cannot be physically deleted when inactive', function () {
    $account = v2MasterChannelAccount($this->v2ChannelOwner->id);
    $account->update(['is_active' => false]);
    expect(fn () => $account->delete())->toThrow(LogicException::class);
    expect(SalesChannelAccount::query()->whereKey($account->id)->exists())->toBeTrue();
});

it('v2 marketplace external events reject unsupported type without creating a registry row', function () {
    $account = v2MasterChannelAccount($this->v2ChannelOwner->id);
    expect(fn () => app(ChannelExternalEventRegistryService::class)->reserve($account, 'inventory_update', 'E-1'))
        ->toThrow(DomainException::class, 'Geçersiz kanal external event tipi');
    expect(ChannelExternalEventRegistry::query()->count())->toBe(0);
});

it('v2 marketplace external events reject blank external identity', function () {
    $account = v2MasterChannelAccount($this->v2ChannelOwner->id);
    expect(fn () => app(ChannelExternalEventRegistryService::class)->reserve($account, 'order', '   '))
        ->toThrow(DomainException::class, 'External event id boş');
    expect(ChannelExternalEventRegistry::query()->count())->toBe(0);
});
