<?php

use App\Foundation\Operations\ProductionSafetyState;
use App\Modules\Core\Models\Company;
use App\Modules\Operations\NotificationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

it('blocks notification delivery before provider execution when the production provider kill-switch is active', function (): void {
    Queue::fake();
    config()->set('mail.default', 'array');
    config()->set('production.recovery_mode', false);
    config()->set('production.outbound_providers_enabled', true);
    config()->set('production.disabled_providers', ['array']);
    app()->forgetInstance(ProductionSafetyState::class);

    $company = Company::query()->create([
        'code' => 'M23-KILL',
        'name' => 'M23 Provider Kill Switch',
    ]);

    $notifications = app(NotificationService::class);
    $deliveryId = $notifications->enqueueRaw(
        (int) $company->getKey(),
        null,
        'email',
        'recipient@example.test',
        'Production safety',
        'This delivery must not leave the application.',
    );

    $notifications->deliver($deliveryId);

    $delivery = DB::table('notification_deliveries')->where('id', $deliveryId)->first();

    expect($delivery)->not->toBeNull()
        ->and((string) $delivery->status)->toBe('failed')
        ->and((string) $delivery->last_error)->toContain('production safety controls')
        ->and(DB::table('notification_provider_attempts')->where('delivery_id', $deliveryId)->count())->toBe(0);
});
