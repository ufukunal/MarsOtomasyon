<?php

it('requires Fresh identity before backing up the exact two isolated databases', function (): void {
    $source = file_get_contents(base_path('ops/uat-fresh-backup-verify.sh'));

    expect($source)->toContain('set -Eeuo pipefail')
        ->toContain('bash "$GATE"')
        ->toContain('mars-uat-fresh-postgres-1')
        ->toContain('for DB in mars_uat_master mars_uat_demo_2026')
        ->toContain('pg_dump -U mars_uat_owner')
        ->toContain('sha256sum -c --status SHA256SUMS');
});

it('tests full restoration without network or access to live Fresh DB volumes', function (): void {
    $source = file_get_contents(base_path('ops/uat-fresh-backup-verify.sh'));

    expect($source)->toContain('--network none')
        ->toContain('mars_fresh_restore_')
        ->toContain('postgres:16 -c listen_addresses=')
        ->toContain('pg_restore -U postgres -d "$TEST_DB"')
        ->toContain('--exit-on-error')
        ->toContain('SRC_COUNT')
        ->toContain('DST_COUNT')
        ->toContain('sudo docker rm -f "$CTR"')
        ->toContain('sudo docker volume rm "$VOL"')
        ->toContain('OFFLINE_RESTORE=PASS')
        ->toContain('ORIGINAL_FRESH_DATABASES_MODIFIED=NO');
});
