<?php

use App\Modules\Communication\ApiAccessTokenService;
use App\Modules\Communication\ScannerAgentService;
use App\Modules\Communication\SystemIntegrationService;
use App\Modules\Core\Models\Company;
use App\Modules\Operations\NotificationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

it('authenticates api tokens without persisting the plaintext secret and enforces permissions', function (): void {
    $company = Company::query()->create(['code' => 'M20-API', 'name' => 'M20 API']);
    $issued = app(ApiAccessTokenService::class)->issue((int) $company->getKey(), 'reference', ['reference.read']);
    $row = DB::table('api_access_tokens')->where('key_id', $issued['key_id'])->firstOrFail();
    expect((string) $row->secret_hash)->not->toContain(substr($issued['token'], 27));

    $this->withToken($issued['token'])->getJson('/api/v1/reference/ping')->assertOk()->assertJsonPath('data.company_id', (int) $company->getKey());
    $this->withToken($issued['token'])->postJson('/api/v1/reference/echo', ['value' => 'x'], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
});

it('replays idempotent api writes and rejects payload drift', function (): void {
    $company = Company::query()->create(['code' => 'M20-IDEM', 'name' => 'M20 Idempotency']);
    $issued = app(ApiAccessTokenService::class)->issue((int) $company->getKey(), 'writer', ['reference.write']);
    $key = (string) Str::uuid();
    $this->withToken($issued['token'])->postJson('/api/v1/reference/echo', ['value' => 'same'], ['Idempotency-Key' => $key])->assertOk();
    $this->withToken($issued['token'])->postJson('/api/v1/reference/echo', ['value' => 'same'], ['Idempotency-Key' => $key])->assertOk()->assertHeader('Idempotent-Replay', 'true');
    $this->withToken($issued['token'])->postJson('/api/v1/reference/echo', ['value' => 'drift'], ['Idempotency-Key' => $key])->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_PAYLOAD_DRIFT');
});

it('rate limits per api token', function (): void {
    config(['m20.api.rate_limit_per_minute' => 2]);
    $company = Company::query()->create(['code' => 'M20-RATE', 'name' => 'M20 Rate']);
    $issued = app(ApiAccessTokenService::class)->issue((int) $company->getKey(), 'reader', ['reference.read']);
    $this->withToken($issued['token'])->getJson('/api/v1/reference/ping')->assertOk();
    $this->withToken($issued['token'])->getJson('/api/v1/reference/ping')->assertOk();
    $this->withToken($issued['token'])->getJson('/api/v1/reference/ping')->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED');
});

it('uses single use scanner enrollment credentials and authenticated job lifecycle', function (): void {
    $company = Company::query()->create(['code' => 'M20-SCAN', 'name' => 'M20 Scanner']);
    $service = app(ScannerAgentService::class);
    $enrollment = $service->issueEnrollmentToken((int) $company->getKey());
    $agent = $service->enroll($enrollment['token'], 'Warehouse Scanner');
    expect(fn () => $service->enroll($enrollment['token'], 'Replay'))->toThrow(DomainException::class);
    $auth = $service->authenticate($agent['agent_token']);
    expect($auth)->not->toBeNull();
    if ($auth === null) {
        throw new RuntimeException('Scanner agent authentication failed.');
    }
    $service->heartbeat($auth['id'], ['scan' => true]);
    $jobKey = (string) Str::uuid();
    $job = $service->enqueue(
        (int) $company->getKey(),
        $agent['public_id'],
        'scan.document',
        ['document' => 'A', 'options' => ['duplex' => true, 'dpi' => 300]],
        $jobKey,
    );
    $replayedJob = $service->enqueue(
        (int) $company->getKey(),
        $agent['public_id'],
        'scan.document',
        ['options' => ['dpi' => 300, 'duplex' => true], 'document' => 'A'],
        $jobKey,
    );
    expect($replayedJob)->toBe($job);
    expect(fn () => $service->enqueue(
        (int) $company->getKey(),
        $agent['public_id'],
        'scan.document',
        ['document' => 'B', 'options' => ['duplex' => true, 'dpi' => 300]],
        $jobKey,
    ))->toThrow(DomainException::class);
    $claim = $service->claim($auth['id']);
    expect($claim)->not->toBeNull()->and($claim['public_id'] ?? null)->toBe($job);
    $service->complete($auth['id'], $job, ['pages' => 1]);
    expect((string) DB::table('scanner_agent_jobs')->where('public_id', $job)->value('status'))->toBe('completed');
});

it('honors the integration kill switch before provider delivery', function (): void {
    Queue::fake();
    Mail::fake();
    $company = Company::query()->create(['code' => 'M20-KILL', 'name' => 'M20 Kill']);
    app(SystemIntegrationService::class)->save((int) $company->getKey(), 'email', 'mail', null, [], [], false);
    $delivery = app(NotificationService::class)->enqueueRaw((int) $company->getKey(), null, 'email', 'test@example.com', 'x', 'body', null, (string) Str::uuid());
    app(NotificationService::class)->deliver($delivery);
    expect((string) DB::table('notification_deliveries')->where('id', $delivery)->value('status'))->toBe('failed')
        ->and(DB::table('notification_provider_attempts')->where('delivery_id', $delivery)->count())->toBe(0);
    Mail::assertNothingSent();
});
