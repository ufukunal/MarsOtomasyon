<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('prevents concurrent local test connections from acquiring the same transaction advisory lock', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $primary = DB::connection('period');
        $lockId = 921230;
        $primary->selectOne('SELECT pg_advisory_xact_lock(?) AS locked', [$lockId]);

        $peerName = 'mars_v4_local_peer';
        config(["database.connections.{$peerName}" => [
            ...config('database.connections.period'),
            'host' => '127.0.0.1',
            'database' => 'mars_test_period',
            'username' => 'mars_test',
        ]]);
        DB::purge($peerName);

        $peer = DB::connection($peerName);

        try {
            $peer->beginTransaction();
            $result = $peer->selectOne(
                'SELECT CASE WHEN pg_try_advisory_xact_lock(?) THEN 1 ELSE 0 END AS acquired',
                [$lockId],
            );

            expect((int) $result->acquired)->toBe(0);
        } finally {
            if ($peer->transactionLevel() > 0) {
                $peer->rollBack();
            }

            DB::disconnect($peerName);
            config(["database.connections.{$peerName}" => null]);
        }
    });
});
