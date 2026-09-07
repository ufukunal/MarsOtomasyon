<?php

use App\Modules\UpdateCenter\UpdateRunState;
use App\Modules\UpdateCenter\UpdateRunStore;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    DB::table('update_runs')->delete();
});

it('creates one idempotent update request for the same request key and payload', function (): void {
    $store = app(UpdateRunStore::class);
    $requestKey = (string) Str::uuid();
    $manifestSha = str_repeat('a', 64);
    $packageSha = str_repeat('b', 64);

    $first = $store->request('1.6.0', 'stable', $manifestSha, $packageSha, null, $requestKey, ['source' => 'admin']);
    $replay = $store->request('1.6.0', 'stable', $manifestSha, $packageSha, null, $requestKey, ['source' => 'ignored-on-replay']);

    expect($replay->id)->toBe($first->id)
        ->and(DB::table('update_runs')->count())->toBe(1)
        ->and($first->status)->toBe(UpdateRunState::Requested->value);
});

it('rejects request key replay drift', function (): void {
    $store = app(UpdateRunStore::class);
    $requestKey = (string) Str::uuid();

    $store->request('1.6.0', 'stable', str_repeat('a', 64), str_repeat('b', 64), null, $requestKey);

    expect(fn () => $store->request('1.6.1', 'stable', str_repeat('a', 64), str_repeat('b', 64), null, $requestKey))
        ->toThrow(DomainException::class, 'payload drift');
});

it('persists forward state transitions and terminal timestamps', function (): void {
    $store = app(UpdateRunStore::class);
    $run = $store->request('1.6.0', 'stable', str_repeat('a', 64), str_repeat('b', 64));

    $verified = $store->transition((int) $run->id, UpdateRunState::Verified, ['verified_by' => 'manifest']);
    $staged = $store->transition((int) $run->id, UpdateRunState::Staged);
    $applying = $store->transition((int) $run->id, UpdateRunState::Applying);
    $health = $store->transition((int) $run->id, UpdateRunState::HealthCheck);
    $activated = $store->transition((int) $run->id, UpdateRunState::Activated);

    expect($verified->status)->toBe('verified')
        ->and($staged->status)->toBe('staged')
        ->and($applying->status)->toBe('applying')
        ->and($health->status)->toBe('health_check')
        ->and($activated->status)->toBe('activated')
        ->and($activated->finished_at)->not->toBeNull();
});

it('records normalized failure without permitting terminal replay', function (): void {
    $store = app(UpdateRunStore::class);
    $run = $store->request('1.6.0', 'stable', str_repeat('a', 64), str_repeat('b', 64));

    $failed = $store->fail((int) $run->id, 'manifest.invalid', 'Manifest rejected.');

    expect($failed->status)->toBe('failed')
        ->and($failed->failure_code)->toBe('manifest.invalid')
        ->and($failed->failure_message)->toBe('Manifest rejected.')
        ->and($failed->finished_at)->not->toBeNull();

    expect(fn () => $store->transition((int) $run->id, UpdateRunState::Verified))
        ->toThrow(DomainException::class);
});
