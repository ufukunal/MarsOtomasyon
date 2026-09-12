<?php

use App\Modules\UpdateCenter\UpdateRunState;
use App\Modules\UpdateCenter\UpdateRunStore;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

it('creates one idempotent request and rejects replay drift', function (): void {
    $store = app(UpdateRunStore::class);
    $key = (string) Str::uuid();
    $manifest = str_repeat('a', 64);
    $package = str_repeat('b', 64);

    $first = $store->request('1.6.0', 'stable', $manifest, $package, null, $key, ['source' => 'test']);
    $replay = $store->request('1.6.0', 'stable', $manifest, $package, null, $key, ['source' => 'ignored']);

    expect($replay->id)->toBe($first->id)
        ->and(DB::table('update_runs')->count())->toBe(1)
        ->and($first->status)->toBe('requested');

    expect(fn () => $store->request('1.6.1', 'stable', $manifest, $package, null, $key))
        ->toThrow(DomainException::class);
});

it('enforces one active update globally', function (): void {
    $store = app(UpdateRunStore::class);
    $store->request('1.6.0', 'stable', str_repeat('a', 64), str_repeat('b', 64), null, (string) Str::uuid());

    expect(fn () => $store->request('1.7.0', 'stable', str_repeat('c', 64), str_repeat('d', 64), null, (string) Str::uuid()))
        ->toThrow(DomainException::class, 'Another update run is already active.');
});

it('persists transitions metadata failure and terminal timestamps', function (): void {
    $store = app(UpdateRunStore::class);
    $run = $store->request('1.6.0', 'beta', str_repeat('a', 64), str_repeat('b', 64), null, (string) Str::uuid(), ['source' => 'admin']);

    $verified = $store->transition((int) $run->id, UpdateRunState::Verified, ['verified' => true]);
    $staged = $store->transition((int) $run->id, UpdateRunState::Staged, ['path' => '/safe/stage']);
    $failed = $store->fail((int) $run->id, 'preflight_failed', "preflight\nfailed", ['disk' => false]);
    $metadata = json_decode((string) $failed->metadata, true, flags: JSON_THROW_ON_ERROR);

    expect($verified->status)->toBe('verified')
        ->and($staged->status)->toBe('staged')
        ->and($failed->status)->toBe('failed')
        ->and($failed->failure_code)->toBe('preflight_failed')
        ->and($failed->failure_message)->toBe('preflight failed')
        ->and($failed->finished_at)->not->toBeNull()
        ->and($metadata)->toMatchArray(['source' => 'admin', 'verified' => true, 'path' => '/safe/stage', 'disk' => false]);
});

it('returns latest history and prevents terminal mutation', function (): void {
    $store = app(UpdateRunStore::class);
    $run = $store->request('1.6.0', 'development', str_repeat('a', 64), str_repeat('b', 64), null, (string) Str::uuid());
    $store->fail((int) $run->id, 'verification_failed', 'Verification failed.');

    expect($store->latest())->toHaveCount(1)
        ->and($store->active())->toBeNull();

    expect(fn () => $store->annotate((int) $run->id, ['late' => true]))
        ->toThrow(DomainException::class, 'Terminal update runs cannot be modified.');
});
