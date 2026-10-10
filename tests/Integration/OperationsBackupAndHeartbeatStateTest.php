<?php

use App\Support\Operations\BackupHealthService;
use App\Support\Operations\OperationalHeartbeatService;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('returns an explicit missing-backup failure instead of treating absent recovery sets as healthy', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $before = config('operations.backup.disks');

        try {
            config(['operations.backup.disks' => []]);
            $status = app(BackupHealthService::class)->check();

            expect($status['ok'])->toBeFalse()
                ->and($status['status'])->toBe('missing')
                ->and($status['severity'])->toBe('failed');
        } finally {
            config(['operations.backup.disks' => $before]);
        }
    });
});

it('upserts one operational heartbeat per service while preserving the latest metadata', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $service = app(OperationalHeartbeatService::class);
        $name = 'v4-lifecycle-local-'.strtolower(\Illuminate\Support\Str::random(8));

        $service->touch($name, ['phase' => 'startup']);
        $service->touch($name, ['phase' => 'healthy']);

        $rows = DB::connection('master')->table('operational_heartbeats')
            ->where('service_key', $name)->get();

        expect($rows)->toHaveCount(1)
            ->and(json_decode($rows[0]->metadata, true))->toBe(['phase' => 'healthy'])
            ->and($rows[0]->last_seen_at)->not->toBeNull();
    });
});
