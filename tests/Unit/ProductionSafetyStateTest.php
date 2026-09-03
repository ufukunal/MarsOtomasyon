<?php

namespace Tests\Unit;

use App\Foundation\Operations\ProductionSafetyState;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProductionSafetyStateTest extends TestCase
{
    public function test_recovery_mode_forces_mutating_operational_capabilities_off(): void
    {
        $state = new ProductionSafetyState(
            recoveryMode: true,
            outboundProvidersEnabled: true,
            asyncWorkEnabled: true,
            schedulerWorkEnabled: true,
            retryAfterSeconds: 120,
            disabledProviders: [],
        );

        self::assertTrue($state->recoveryMode());
        self::assertFalse($state->mutationsAllowed());
        self::assertFalse($state->outboundProvidersEnabled());
        self::assertFalse($state->asyncWorkEnabled());
        self::assertFalse($state->schedulerWorkEnabled());
        self::assertFalse($state->providerEnabled('trendyol'));
        self::assertSame(120, $state->retryAfterSeconds());
    }

    public function test_provider_kill_switch_supports_global_and_per_provider_controls(): void
    {
        $state = new ProductionSafetyState(
            recoveryMode: false,
            outboundProvidersEnabled: true,
            asyncWorkEnabled: true,
            schedulerWorkEnabled: true,
            retryAfterSeconds: 300,
            disabledProviders: ['trendyol'],
        );

        self::assertFalse($state->providerEnabled('trendyol'));
        self::assertTrue($state->providerEnabled('woocommerce'));

        $this->expectException(RuntimeException::class);
        $state->assertProviderEnabled('trendyol');
    }

    public function test_runtime_recovery_mode_is_shared_through_the_safety_store(): void
    {
        $store = new Repository(new ArrayStore);
        $first = new ProductionSafetyState(false, true, true, true, 300, [], $store, 'm23:recovery');
        $second = new ProductionSafetyState(false, true, true, true, 300, [], $store, 'm23:recovery');

        $first->enterRecoveryMode();

        self::assertTrue($second->recoveryMode());
        self::assertFalse($second->mutationsAllowed());

        $second->leaveRecoveryMode();

        self::assertFalse($first->recoveryMode());
    }
}
