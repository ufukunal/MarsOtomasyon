<?php

namespace Tests\Feature;

use App\Foundation\Operations\ProductionSafetyState;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class RecoveryModeCommandTest extends TestCase
{
    public function test_recovery_mode_command_transitions_the_shared_safety_state(): void
    {
        $store = new Repository(new ArrayStore);
        $state = new ProductionSafetyState(
            recoveryMode: false,
            outboundProvidersEnabled: true,
            asyncWorkEnabled: true,
            schedulerWorkEnabled: true,
            retryAfterSeconds: 300,
            disabledProviders: [],
            store: $store,
            recoveryStateKey: 'm23:test:recovery-mode',
        );
        $this->app->instance(ProductionSafetyState::class, $state);

        self::assertSame(0, Artisan::call('mars:recovery-mode', ['action' => 'status']));
        self::assertStringContainsString('recovery-mode:off', Artisan::output());

        self::assertSame(0, Artisan::call('mars:recovery-mode', ['action' => 'on']));
        self::assertTrue($state->recoveryMode());
        self::assertFalse($state->mutationsAllowed());

        self::assertSame(0, Artisan::call('mars:recovery-mode', ['action' => 'status']));
        self::assertStringContainsString('recovery-mode:on', Artisan::output());

        self::assertSame(0, Artisan::call('mars:recovery-mode', ['action' => 'off']));
        self::assertFalse($state->recoveryMode());
        self::assertTrue($state->mutationsAllowed());
    }

    public function test_recovery_mode_command_rejects_unknown_actions(): void
    {
        $state = new ProductionSafetyState(false, true, true, true, 300, []);
        $this->app->instance(ProductionSafetyState::class, $state);

        self::assertSame(2, Artisan::call('mars:recovery-mode', ['action' => 'unexpected']));
        self::assertStringContainsString('Action must be one of: status, on, off.', Artisan::output());
        self::assertFalse($state->recoveryMode());
    }
}
