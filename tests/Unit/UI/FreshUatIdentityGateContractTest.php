<?php

it('refuses any identity other than the isolated Fresh UAT stack before database operations', function (): void {
    $script = file_get_contents(base_path('ops/uat-fresh-identity-check.sh'));

    expect($script)->toContain('set -Eeuo pipefail')
        ->toContain("PROJECT=mars-uat-fresh")
        ->toContain('mars-uat-fresh_fresh_pg')
        ->toContain('mars-uat-fresh_fresh_valkey')
        ->toContain('mars-uat-fresh_fresh_storage')
        ->toContain('mars_uat_demo_2026,mars_uat_master')
        ->toContain("database_name='mars_uat_demo_2026'")
        ->toContain("status='active'")
        ->toContain('mars:fresh:')
        ->toContain('uat|mars_uat_master|mars_uat_demo_2026|mars:fresh:|MarsProjectFreshUAT')
        ->toContain('READ_ONLY=YES');
});
