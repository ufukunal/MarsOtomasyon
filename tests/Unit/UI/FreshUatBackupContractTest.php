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
        ->toContain('RESTORE_VERIFIED')
        ->toContain('SHA256SUMS.sha256')
        ->toContain('OFFLINE_RESTORE=PASS')
        ->toContain('ORIGINAL_FRESH_DATABASES_MODIFIED=NO');
});

it('refuses Fresh schema migrations without the matching restore-verified backup', function (): void {
    $script = file_get_contents(base_path('ops/uat-fresh-period-migrations.sh'));

    expect($script)->toContain('bash "$GATE"')
        ->toContain('FRESH_BACKUP=RESTORE_VERIFIED')
        ->toContain('SOURCE_PERIOD=mars_uat_demo_2026')
        ->toContain('sha256sum -c --status SHA256SUMS')
        ->toContain('0001_12_02_000010_add_document_additional_discount.php')
        ->toContain('0001_12_02_000020_create_contact_transfers.php')
        ->toContain('DB_PERIOD_DATABASE=mars_uat_demo_2026')
        ->toContain('--database=period')
        ->toContain('--no-deps')
        ->toContain('VERIFIED_EPOCH')
        ->toContain('-le 3600')
        ->toContain('FRESH_PERIOD_MIGRATIONS=PASS');
});


it('orchestrates 18 through 21 sequentially without skipping identity and restore proofs', function (): void {
    $source = file_get_contents(base_path('ops/uat-fresh-stage-18-21.sh'));
    expect($source)->toContain('set -Eeuo pipefail')
        ->toContain('bash "$SCRIPT_DIR/uat-fresh-identity-check.sh"')
        ->toContain('bash "$SCRIPT_DIR/uat-fresh-backup-verify.sh"')
        ->toContain("grep -qx 'OFFLINE_RESTORE=PASS'")
        ->toContain('bash "$SCRIPT_DIR/uat-fresh-period-migrations.sh" "$BACKUP_PATH"')
        ->toContain('FRESH_18_21=PASS');
    expect(strpos($source, 'uat-fresh-identity-check.sh" || abort'))
        ->toBeLessThan(strpos($source, 'uat-fresh-backup-verify.sh" | tee'))
        ->and(strpos($source, 'uat-fresh-backup-verify.sh" | tee'))
        ->toBeLessThan(strpos($source, 'uat-fresh-period-migrations.sh" "$BACKUP_PATH"'));
});

it('exports only the approved Fresh UAT scripts and period migrations from CI', function (): void {
    $source = file_get_contents(base_path('ops/uat-fresh-export-release.sh'));
    expect($source)->toContain('git archive --format=tar HEAD')
        ->toContain('ops/uat-fresh-stage-18-21.sh')
        ->toContain('ops/uat-fresh-identity-check.sh')
        ->toContain('ops/uat-fresh-backup-verify.sh')
        ->toContain('ops/uat-fresh-period-migrations.sh')
        ->toContain('0001_12_02_000010_add_document_additional_discount.php')
        ->toContain('0001_12_02_000020_create_contact_transfers.php')
        ->toContain('sha256sum')
        ->not->toContain('docker compose up');
});
