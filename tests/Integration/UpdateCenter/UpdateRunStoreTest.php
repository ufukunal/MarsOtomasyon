<?php

use App\Modules\UpdateCenter\UpdateRunState;
use App\Modules\UpdateCenter\UpdateRunStore;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

uses(DatabaseMigrations::class);

it('creates one idempotent update request for the same request key and payload', function (): void {
    $store = app(UpdateRunStore::class);
    $requestKey = (string) Str::uuid();
    $manifestSha = str_repeat('a', 64);
    $packageSha = str_repeat('b', 64);

    $first = $store->request('1.6.0', 'stable', $manifestSha, $packageSha, null, $requestKey, ['source' => 'admin']);
    $replay = $store->request('1.6.0', 'stable', $manifestSha, $packageSha, null, $requestKey, ['source' => 'ignored']);

    expect($replay->id)->toBe($first->id)
        ->and(DB::table('update_runs')->count())->toBe(1)
        ->and($first->status)->toBe(UpdateRunState::Requested->value);
});

it('rejects request key replay drift and malformed control fields', function (): void {
    $store = app(UpdateRunStore::class);
    $requestKey = (string) Str::uuid();
    $store->request('1.6.0', 'stable', str_repeat('a', 64), str_repeat('b', 64), null, $requestKey);

    expect(fn () => $store->request('1.6.1', 'stable', str_repeat('a', 64), str_repeat('b', 64), null, $requestKey))
        ->toThrow(DomainException::class, 'payload drift');
    expect(fn () => $store->request('latest', 'stable', str_repeat('a', 64), str_repeat('b', 64)))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $store->request('1.6.0', 'nightly', str_repeat('a', 64), str_repeat('b', 64)))
        ->toThrow(DomainException::class, 'channel');
});

it('persists a verified staged artifact before apply may be requested', function (): void {
    $store = app(UpdateRunStore::class);
    $run = $store->request('1.6.0', 'stable', str_repeat('a', 64), str_repeat('b', 64));
    $store->transition((int) $run->id, UpdateRunState::Verified);
    $store->transition((int) $run->id, UpdateRunState::Staging);

    $artifact = $store->recordArtifact(
        (int) $run->id,
        '/safe/package.zip',
        '/safe/release',
        str_repeat('b', 64),
        1024,
        12,
        4096,
    );
    $staged = $store->find((int) $run->id);

    expect($artifact->update_run_id)->toBe($run->id)
        ->and($artifact->file_count)->toBe(12)
        ->and($staged->status)->toBe(UpdateRunState::Staged->value);
});

it('claims exactly one queued apply request and advances it to backup creation', function (): void {
    $store = app(UpdateRunStore::class);
    $run = $store->request('1.6.0', 'stable', str_repeat('a', 64), str_repeat('b', 64));
    $store->transition((int) $run->id, UpdateRunState::Verified);
    $store->transition((int) $run->id, UpdateRunState::Staging);
    $store->recordArtifact((int) $run->id, '/safe/package.zip', '/safe/release', str_repeat('b', 64), 1024, 1, 2048);
    $store->transition((int) $run->id, UpdateRunState::ApplyRequested);

    $claimed = $store->claimNextApply();

    expect($claimed)->not->toBeNull()
        ->and($claimed?->status)->toBe(UpdateRunState::BackupCreating->value)
        ->and($store->claimNextApply())->toBeNull();
});

it('records normalized failures and rejects invalid terminal transitions', function (): void {
    $store = app(UpdateRunStore::class);
    $run = $store->request('1.6.0', 'stable', str_repeat('a', 64), str_repeat('b', 64));
    $failed = $store->fail((int) $run->id, 'manifest.invalid', 'Manifest rejected.');

    expect($failed->status)->toBe(UpdateRunState::Failed->value)
        ->and($failed->failure_code)->toBe('manifest.invalid')
        ->and($failed->finished_at)->not->toBeNull();
    expect(fn () => $store->transition((int) $run->id, UpdateRunState::Verified))
        ->toThrow(DomainException::class);
});
