<?php

use App\Modules\Core\Models\Company;
use App\Modules\Operations\AutomationService;
use App\Modules\Operations\BackupManager;
use App\Modules\Operations\ChannelService;
use App\Modules\Operations\NotificationService;
use App\Modules\Operations\OperationsHealth;
use App\Modules\Operations\SecurityCenter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

it('ingests signed WooCommerce webhooks exactly once and rejects replay drift while secrets remain encrypted', function (): void {
    Queue::fake();
    $company = Company::query()->create(['code' => 'M11-WOO', 'name' => 'M11 Woo']);
    $channels = app(ChannelService::class);
    $connectionId = $channels->createConnection(
        (int) $company->getKey(),
        'woocommerce',
        'Primary Shop',
        'https://shop.example.test',
        ['consumer_key' => 'ck_test_plain', 'consumer_secret' => 'cs_test_plain'],
        'webhook-secret-m11',
    );

    $raw = json_encode(['id' => 42, 'status' => 'processing'], JSON_THROW_ON_ERROR);
    $signature = base64_encode(hash_hmac('sha256', $raw, 'webhook-secret-m11', true));
    $first = $channels->ingestWebhook($connectionId, 'evt-42', 'order.created', $raw, $signature);
    $replay = $channels->ingestWebhook($connectionId, 'evt-42', 'order.created', $raw, $signature);

    expect($replay)->toBe($first)
        ->and(DB::table('integration_events')->count())->toBe(1);

    $connection = DB::table('integration_connections')->where('id', $connectionId)->first();
    expect($connection)->not->toBeNull()
        ->and((string) $connection->credentials_ciphertext)->not->toContain('ck_test_plain')
        ->and((string) $connection->webhook_secret_ciphertext)->not->toContain('webhook-secret-m11');

    $drift = json_encode(['id' => 42, 'status' => 'completed'], JSON_THROW_ON_ERROR);
    $driftSignature = base64_encode(hash_hmac('sha256', $drift, 'webhook-secret-m11', true));
    expect(fn () => $channels->ingestWebhook($connectionId, 'evt-42', 'order.created', $drift, $driftSignature))
        ->toThrow(\DomainException::class, 'payload drift');

    expect(fn () => DB::table('integration_events')->where('id', $first)->update(['payload' => json_encode(['forged' => true], JSON_THROW_ON_ERROR)]))
        ->toThrow(QueryException::class);
});

it('deduplicates notification delivery and protects immutable delivery content at PostgreSQL boundary', function (): void {
    Queue::fake();
    $company = Company::query()->create(['code' => 'M11-NOTIFY', 'name' => 'M11 Notify']);
    $templateId = DB::table('notification_templates')->insertGetId([
        'company_id' => $company->getKey(),
        'key' => 'order.ready',
        'channel' => 'email',
        'name' => 'Order ready',
        'status' => 'active',
        'subject' => 'Sipariş {{number}}',
        'body' => 'Sipariş {{number}} hazır.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $key = (string) Str::uuid();
    $notifications = app(NotificationService::class);
    $first = $notifications->enqueueTemplate((int) $company->getKey(), 'order.ready', 'email', 'test@example.com', ['number' => 'SO-1'], $key);
    $replay = $notifications->enqueueTemplate((int) $company->getKey(), 'order.ready', 'email', 'test@example.com', ['number' => 'SO-1'], $key);

    expect($replay)->toBe($first)
        ->and(DB::table('notification_deliveries')->count())->toBe(1)
        ->and((int) DB::table('notification_deliveries')->where('id', $first)->value('template_id'))->toBe($templateId);

    expect(fn () => $notifications->enqueueRaw((int) $company->getKey(), $templateId, 'email', 'other@example.com', null, 'Changed', null, $key))
        ->toThrow(\DomainException::class, 'payload drift');
    expect(fn () => DB::table('notification_deliveries')->where('id', $first)->update(['body' => 'forged']))
        ->toThrow(QueryException::class);
});

it('requires approval for configured automation and appends an immutable security event exactly once', function (): void {
    Queue::fake();
    $company = Company::query()->create(['code' => 'M11-AUTO', 'name' => 'M11 Auto']);
    $userId = (int) DB::table('users')->insertGetId([
        'name' => 'M11 Approver', 'email' => 'm11-approver@example.test', 'password' => 'not-used', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $ruleId = (int) DB::table('automation_rules')->insertGetId([
        'company_id' => $company->getKey(),
        'key' => 'security.order_paid',
        'name' => 'Record paid order',
        'event_type' => 'channel.order.paid',
        'conditions' => json_encode(['status' => 'paid'], JSON_THROW_ON_ERROR),
        'action_type' => 'security_event',
        'action_payload' => json_encode(['event_type' => 'automation.order_paid', 'severity' => 'warning'], JSON_THROW_ON_ERROR),
        'requires_approval' => true,
        'is_enabled' => true,
        'priority' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $automation = app(AutomationService::class);
    expect($automation->fire((int) $company->getKey(), 'channel.order.paid', 'order:1001', ['status' => 'paid', 'order_id' => 1001]))->toBe(1)
        ->and($automation->fire((int) $company->getKey(), 'channel.order.paid', 'order:1001', ['status' => 'paid', 'order_id' => 1001]))->toBe(0);

    $run = DB::table('automation_runs')->where('rule_id', $ruleId)->first();
    expect($run)->not->toBeNull()->and((string) $run->status)->toBe('pending_approval');
    $automation->approve((int) $company->getKey(), (int) $run->id, $userId);
    $automation->execute((int) $run->id, app(NotificationService::class), app(ChannelService::class), app(SecurityCenter::class));

    expect(DB::table('automation_runs')->where('id', $run->id)->value('status'))->toBe('succeeded')
        ->and(DB::table('security_events')->where('event_type', 'automation.order_paid')->count())->toBe(1);

    $eventId = (int) DB::table('security_events')->where('event_type', 'automation.order_paid')->value('id');
    expect(fn () => DB::table('security_events')->where('id', $eventId)->delete())->toThrow(QueryException::class);
});

it('tracks worker and scheduler heartbeats and provisions durable queue infrastructure', function (): void {
    $health = app(OperationsHealth::class);
    $health->heartbeat('worker', 'worker-1', ['pid' => 10]);
    $health->heartbeat('scheduler', 'scheduler-1', ['pid' => 11]);

    expect(DB::table('operations_heartbeats')->where('component', 'worker')->count())->toBe(1)
        ->and(DB::table('operations_heartbeats')->where('component', 'scheduler')->count())->toBe(1)
        ->and(DB::getSchemaBuilder()->hasTable('jobs'))->toBeTrue()
        ->and(DB::getSchemaBuilder()->hasTable('job_batches'))->toBeTrue()
        ->and(DB::getSchemaBuilder()->hasTable('failed_jobs'))->toBeTrue()
        ->and(Route::has('operations.index'))->toBeTrue()
        ->and(Route::has('channels.webhook'))->toBeTrue()
        ->and(Route::has('commerce.index'))->toBeTrue()
        ->and(Route::has('communications.index'))->toBeTrue();
});

it('verifies encrypted marsbak artifacts by sha256 and detects tampering before restore', function (): void {
    Storage::fake('local');
    $id = (string) Str::uuid();
    $path = 'backups/mars-'.$id.'.marsbak';
    $contents = json_encode(['format' => 'marsbak-v1', 'ciphertext' => 'opaque-encrypted-payload'], JSON_THROW_ON_ERROR);
    Storage::disk('local')->put($path, $contents);
    DB::table('backup_artifacts')->insert([
        'id' => $id,
        'status' => 'ready',
        'disk' => 'local',
        'path' => $path,
        'sha256' => hash('sha256', $contents),
        'size_bytes' => strlen($contents),
        'encrypted' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $backups = app(BackupManager::class);
    expect($backups->verify($id))->toBeTrue();
    Storage::disk('local')->put($path, $contents.'tampered');
    expect($backups->verify($id))->toBeFalse();
});
