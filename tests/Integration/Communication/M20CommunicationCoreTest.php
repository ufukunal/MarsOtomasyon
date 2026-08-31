<?php

use App\Modules\Communication\NotificationTemplateService;
use App\Modules\Communication\SystemIntegrationService;
use App\Modules\Communication\SystemIntegrationSummary;
use App\Modules\Core\Models\Company;
use App\Modules\Operations\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

it('stores system integration credentials encrypted and only exposes masked configuration state', function (): void {
    $company = Company::query()->create(['code' => 'M20-INT', 'name' => 'M20 Integration']);
    $service = app(SystemIntegrationService::class);
    $service->save(
        (int) $company->getKey(),
        'sms',
        'provider_candidate',
        'https://sms.example.test',
        ['sender' => 'MARS'],
        ['api_key' => 'm20-secret-key'],
        true,
    );

    $row = DB::table('system_integration_settings')->where('company_id', $company->getKey())->where('family', 'sms')->firstOrFail();
    expect((string) $row->credentials_ciphertext)->not->toContain('m20-secret-key')
        ->and((string) $row->verification_status)->toBe('unverified');

    $service->validateConfiguration((int) $company->getKey(), 'sms');
    $summary = $service->summaries((int) $company->getKey())->firstWhere('family', 'sms');
    expect($summary)->toBeInstanceOf(SystemIntegrationSummary::class);
    if (! $summary instanceof SystemIntegrationSummary) {
        throw new RuntimeException('SMS integration summary was not returned.');
    }
    expect($summary->hasCredentials)->toBeTrue()
        ->and($summary->verificationStatus)->toBe('configuration_validated')
        ->and(property_exists($summary, 'credentialsCiphertext'))->toBeFalse();
});

it('creates immutable notification template versions and advances the current pointer only on material change', function (): void {
    $company = Company::query()->create(['code' => 'M20-TPL', 'name' => 'M20 Templates']);
    $templates = app(NotificationTemplateService::class);
    $id = $templates->store((int) $company->getKey(), 'order.ready', 'email', 'Order Ready', 'Sipariş {{number}}', 'Sipariş {{number}} hazır.', ['number']);
    $templates->store((int) $company->getKey(), 'order.ready', 'email', 'Order Ready', 'Sipariş {{number}}', 'Sipariş {{number}} hazır.', ['number']);
    $templates->store((int) $company->getKey(), 'order.ready', 'email', 'Order Ready', 'Sipariş {{number}}', 'Sipariş {{number}} sevke hazır.', ['number']);

    expect((int) DB::table('notification_templates')->where('id', $id)->value('current_version'))->toBe(2)
        ->and(DB::table('notification_template_versions')->where('template_id', $id)->count())->toBe(2);

    $versionId = (int) DB::table('notification_template_versions')->where('template_id', $id)->where('version', 1)->value('id');
    expect(fn () => DB::table('notification_template_versions')->where('id', $versionId)->update(['body' => 'forged']))
        ->toThrow(QueryException::class);
});

it('pins notification delivery to a template version and records each provider attempt', function (): void {
    Queue::fake();
    Mail::fake();
    $company = Company::query()->create(['code' => 'M20-ATTEMPT', 'name' => 'M20 Attempt']);
    app(NotificationTemplateService::class)->store(
        (int) $company->getKey(),
        'invoice.ready',
        'email',
        'Invoice Ready',
        'Fatura {{number}}',
        'Fatura {{number}} hazır.',
        ['number'],
    );
    $notifications = app(NotificationService::class);
    $deliveryId = $notifications->enqueueTemplate(
        (int) $company->getKey(),
        'invoice.ready',
        'email',
        'test@example.com',
        ['number' => 'INV-1'],
        (string) Str::uuid(),
    );
    $notifications->deliver($deliveryId);

    $delivery = DB::table('notification_deliveries')->where('id', $deliveryId)->firstOrFail();
    $attempt = DB::table('notification_provider_attempts')->where('delivery_id', $deliveryId)->firstOrFail();
    expect((int) $delivery->template_version)->toBe(1)
        ->and((string) $delivery->status)->toBe('sent')
        ->and((int) $attempt->attempt_no)->toBe(1)
        ->and((string) $attempt->status)->toBe('succeeded');
});

it('fails template preview when declared data leaves unresolved placeholders', function (): void {
    $templates = app(NotificationTemplateService::class);
    expect(fn () => $templates->preview('', 'Sipariş {{number}} / {{customer}}', ['number' => 'SO-1']))
        ->toThrow(DomainException::class, 'unresolved variables');
});
