<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Operations\BackupManager;
use App\Modules\Operations\ChannelDomainEventIngestor;
use App\Modules\Operations\ChannelService;
use App\Modules\Operations\Jobs\DeliverNotification;
use App\Modules\Operations\Jobs\ExecuteAutomationRun;
use App\Modules\Operations\Jobs\ProcessIntegrationEvent;
use App\Modules\Operations\Jobs\ProcessIntegrationSync;
use App\Modules\Operations\OperationsHealth;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

it('atomically reclaims stale M11 work and requeues the correct durable jobs', function (): void {
    Queue::fake();
    $company = Company::query()->create(['code' => 'M11-RECOVERY', 'name' => 'M11 Recovery']);
    $connectionId = app(ChannelService::class)->createConnection(
        (int) $company->getKey(),
        'woocommerce',
        'Recovery Shop',
        'https://shop.example.test',
        [],
        'm11-recovery-webhook-secret',
    );
    $stale = now()->subMinutes(20);

    $eventPayload = json_encode(['id' => 101], JSON_THROW_ON_ERROR);
    $eventId = (int) DB::table('integration_events')->insertGetId([
        'company_id' => $company->getKey(),
        'connection_id' => $connectionId,
        'external_event_id' => 'stale-event-101',
        'event_type' => 'order.created',
        'payload_sha256' => hash('sha256', $eventPayload),
        'payload' => $eventPayload,
        'status' => 'processing',
        'attempts' => 1,
        'available_at' => $stale,
        'created_at' => $stale,
        'updated_at' => $stale,
    ]);

    $syncPayload = json_encode(['id' => 202], JSON_THROW_ON_ERROR);
    $syncId = (int) DB::table('integration_sync_effects')->insertGetId([
        'company_id' => $company->getKey(),
        'connection_id' => $connectionId,
        'operation_key' => (string) Str::uuid(),
        'operation' => 'order',
        'entity_type' => 'sales_order',
        'entity_id' => '202',
        'payload_sha256' => hash('sha256', $syncPayload),
        'payload' => $syncPayload,
        'status' => 'sending',
        'attempts' => 1,
        'available_at' => $stale,
        'created_at' => $stale,
        'updated_at' => $stale,
    ]);

    $deliveryId = (int) DB::table('notification_deliveries')->insertGetId([
        'company_id' => $company->getKey(),
        'template_id' => null,
        'idempotency_key' => (string) Str::uuid(),
        'channel' => 'email',
        'recipient' => 'recovery@example.test',
        'subject' => 'Recovery',
        'body' => 'Recovery body',
        'status' => 'sending',
        'attempts' => 1,
        'available_at' => $stale,
        'created_at' => $stale,
        'updated_at' => $stale,
    ]);

    $ruleId = (int) DB::table('automation_rules')->insertGetId([
        'company_id' => $company->getKey(),
        'key' => 'recovery.rule',
        'name' => 'Recovery Rule',
        'event_type' => 'recovery.event',
        'conditions' => null,
        'action_type' => 'security_event',
        'action_payload' => json_encode(['event_type' => 'recovery.completed'], JSON_THROW_ON_ERROR),
        'requires_approval' => false,
        'is_enabled' => true,
        'priority' => 100,
        'created_at' => $stale,
        'updated_at' => $stale,
    ]);
    $runId = (int) DB::table('automation_runs')->insertGetId([
        'company_id' => $company->getKey(),
        'rule_id' => $ruleId,
        'trigger_key' => 'recovery:303',
        'status' => 'running',
        'input' => json_encode(['id' => 303], JSON_THROW_ON_ERROR),
        'started_at' => $stale,
        'created_at' => $stale,
        'updated_at' => $stale,
    ]);

    expect(app(OperationsHealth::class)->recoverStaleWork())->toBe(4)
        ->and(DB::table('integration_events')->where('id', $eventId)->value('status'))->toBe('received')
        ->and(DB::table('integration_sync_effects')->where('id', $syncId)->value('status'))->toBe('queued')
        ->and(DB::table('notification_deliveries')->where('id', $deliveryId)->value('status'))->toBe('queued')
        ->and(DB::table('automation_runs')->where('id', $runId)->value('status'))->toBe('queued')
        ->and(DB::table('automation_runs')->where('id', $runId)->value('started_at'))->toBeNull();

    Queue::assertPushed(ProcessIntegrationEvent::class, fn (ProcessIntegrationEvent $job): bool => $job->eventId === $eventId);
    Queue::assertPushed(ProcessIntegrationSync::class, fn (ProcessIntegrationSync $job): bool => $job->effectId === $syncId);
    Queue::assertPushed(DeliverNotification::class, fn (DeliverNotification $job): bool => $job->deliveryId === $deliveryId);
    Queue::assertPushed(ExecuteAutomationRun::class, fn (ExecuteAutomationRun $job): bool => $job->runId === $runId);
});

it('requires platform administrator authority in addition to tenant backup permissions', function (): void {
    $company = Company::query()->create(['code' => 'M11-BACKUP-AUTH', 'name' => 'M11 Backup Auth']);
    $user = m11FinalizationUserWithPermissions($company, [PermissionKey::BackupView, PermissionKey::BackupManage], 'backup-auth');
    $context = app(ActiveCompanyContext::class);
    $context->set($company);

    try {
        expect(Gate::forUser($user)->allows(PermissionKey::BackupView->value))->toBeFalse()
            ->and(Gate::forUser($user)->allows(PermissionKey::BackupManage->value))->toBeFalse();

        $user->forceFill(['is_platform_admin' => true])->save();
        $user->refresh();

        expect(Gate::forUser($user)->allows(PermissionKey::BackupView->value))->toBeTrue()
            ->and(Gate::forUser($user)->allows(PermissionKey::BackupManage->value))->toBeTrue();
    } finally {
        $context->clear();
    }
});

it('enforces company IP deny policy on tenant modules outside the operations routes', function (): void {
    $company = Company::query()->create(['code' => 'M11-IP', 'name' => 'M11 IP']);
    $user = m11FinalizationUserWithPermissions($company, [PermissionKey::AccountView], 'ip-user');
    DB::table('security_ip_rules')->insert([
        'company_id' => $company->getKey(),
        'action' => 'deny',
        'cidr' => '203.0.113.10/32',
        'label' => 'Regression deny',
        'is_active' => true,
        'created_by_user_id' => $user->getKey(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_company_id' => $company->getKey()])
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->get('/accounts')
        ->assertForbidden();

    expect(DB::table('security_events')
        ->where('company_id', $company->getKey())
        ->where('event_type', 'security.ip_blocked')
        ->where('ip_address', '203.0.113.10')
        ->count())->toBe(1);
});

it('maps a WooCommerce order into one idempotently linked Mars sales order by SKU', function (): void {
    Queue::fake();
    [$company, $customer, $product] = m11FinalizationCommerceFixture();
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesOrder,
        'series_code' => 'web',
        'prefix' => 'WEB-',
        'padding' => 6,
        'next_value' => 1,
        'is_active' => true,
    ]);

    $channels = app(ChannelService::class);
    $connectionId = $channels->createConnection(
        (int) $company->getKey(),
        'woocommerce',
        'Commerce Shop',
        'https://shop.example.test',
        [
            'default_account_id' => (int) $customer->getKey(),
            'price_basis' => 'net',
            'order_series' => 'web',
        ],
        'm11-commerce-webhook-secret',
    );
    $payload = [
        'id' => 9001,
        'currency' => 'TRY',
        'date_created' => '2026-08-28T10:00:00+03:00',
        'line_items' => [[
            'sku' => (string) $product->code,
            'name' => (string) $product->name,
            'quantity' => 2,
            'price' => '120.000000',
        ]],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = base64_encode(hash_hmac('sha256', $raw, 'm11-commerce-webhook-secret', true));
    $eventId = $channels->ingestWebhook($connectionId, 'woo-order-9001', 'order.created', $raw, $signature);
    $domain = app(ChannelDomainEventIngestor::class);

    $first = $domain->process($eventId);
    $replay = $domain->process($eventId);

    expect($first)->not->toBeNull()
        ->and($replay)->not->toBeNull()
        ->and($replay['local_id'])->toBe($first['local_id'])
        ->and(DB::table('sales_orders')->where('company_id', $company->getKey())->count())->toBe(1)
        ->and(DB::table('integration_entity_links')
            ->where('company_id', $company->getKey())
            ->where('connection_id', $connectionId)
            ->where('entity_type', 'order')
            ->where('external_id', '9001')
            ->count())->toBe(1)
        ->and(DB::table('sales_order_lines')->where('sales_order_id', $first['local_id'])->value('quantity'))->toBe('2.000000');
});

it('verifies marsbak v2 file manifests and rejects internal file payload drift even with a valid outer sha', function (): void {
    Storage::fake('local');
    $id = (string) Str::uuid();
    $path = 'backups/mars-'.$id.'.marsbak';
    $fileContents = 'private-asset-payload';
    $fileSha = hash('sha256', $fileContents);
    $payload = json_encode([
        'version' => 2,
        'sql' => '-- deterministic postgres dump --',
        'file_assets' => [[
            'disk' => 'local',
            'key' => 'companies/1/files/example',
            'sha256' => $fileSha,
            'size_bytes' => strlen($fileContents),
            'contents' => base64_encode($fileContents),
        ]],
    ], JSON_THROW_ON_ERROR);
    $wrapper = json_encode([
        'format' => 'marsbak-v2',
        'created_at' => now()->toIso8601String(),
        'ciphertext' => Crypt::encryptString($payload),
    ], JSON_THROW_ON_ERROR);
    Storage::disk('local')->put($path, $wrapper);
    DB::table('backup_artifacts')->insert([
        'id' => $id,
        'status' => 'ready',
        'disk' => 'local',
        'path' => $path,
        'sha256' => hash('sha256', $wrapper),
        'size_bytes' => strlen($wrapper),
        'encrypted' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $backups = app(BackupManager::class);
    expect($backups->verify($id))->toBeTrue();

    $tamperedPayload = json_encode([
        'version' => 2,
        'sql' => '-- deterministic postgres dump --',
        'file_assets' => [[
            'disk' => 'local',
            'key' => 'companies/1/files/example',
            'sha256' => $fileSha,
            'size_bytes' => strlen($fileContents),
            'contents' => base64_encode('tampered-file-payload'),
        ]],
    ], JSON_THROW_ON_ERROR);
    $tamperedWrapper = json_encode([
        'format' => 'marsbak-v2',
        'created_at' => now()->toIso8601String(),
        'ciphertext' => Crypt::encryptString($tamperedPayload),
    ], JSON_THROW_ON_ERROR);
    Storage::disk('local')->put($path, $tamperedWrapper);
    DB::table('backup_artifacts')->where('id', $id)->update([
        'sha256' => hash('sha256', $tamperedWrapper),
        'size_bytes' => strlen($tamperedWrapper),
        'updated_at' => now(),
    ]);

    expect($backups->verify($id))->toBeFalse();
});

function m11FinalizationUserWithPermissions(Company $company, array $permissions, string $suffix): User
{
    $user = User::query()->create([
        'name' => 'M11 '.$suffix,
        'email' => 'm11-'.$suffix.'@example.test',
        'password' => 'm11-regression-password',
        'status' => UserStatus::Active,
        'is_platform_admin' => false,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'm11-'.$suffix,
        'name' => 'M11 '.$suffix,
        'is_active' => true,
    ]);

    foreach ($permissions as $permission) {
        $permissionId = DB::table('permissions')->where('key', $permission->value)->value('id');
        if (! is_int($permissionId)) {
            throw new RuntimeException('M11 regression permission is missing: '.$permission->value);
        }
        DB::table('role_permissions')->insert([
            'role_id' => $role->getKey(),
            'permission_id' => $permissionId,
        ]);
    }
    DB::table('company_membership_roles')->insert([
        'company_id' => $company->getKey(),
        'membership_id' => $membership->getKey(),
        'role_id' => $role->getKey(),
        'assigned_at' => now(),
    ]);

    return $user;
}

/** @return array{Company, Account, Product} */
function m11FinalizationCommerceFixture(): array
{
    $company = Company::query()->create(['code' => 'M11-COMMERCE', 'name' => 'M11 Commerce']);
    $customer = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'WEB-CUSTOMER',
        'type' => AccountType::Customer,
        'status' => AccountStatus::Active,
        'legal_name' => 'Web Customer',
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'WEB',
        'name' => 'Web',
        'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'ADET',
        'name' => 'Adet',
        'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'KDV20',
        'name' => 'KDV %20',
        'rate' => '20.000000',
        'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'WEB-SKU-1',
        'status' => ProductStatus::Active,
        'name' => 'Web Product',
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '120.000000',
        'purchase_price_net' => '80.000000',
    ]);

    return [$company, $customer, $product];
}
