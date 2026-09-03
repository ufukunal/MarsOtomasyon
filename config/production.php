<?php

return [
    'deployment_model' => env('MARS_DEPLOYMENT_MODEL', 'docker-compose'),
    'primary_file_disk' => env('MARS_PRIMARY_FILE_DISK', 'local'),
    'recovery_mode' => (bool) env('MARS_RECOVERY_MODE', false),
    'recovery_state_store' => env('MARS_RECOVERY_STATE_STORE', env('CACHE_STORE', 'redis')),
    'recovery_state_key' => env('MARS_RECOVERY_STATE_KEY', 'mars:production:recovery-mode'),
    'outbound_providers_enabled' => (bool) env('MARS_OUTBOUND_PROVIDERS_ENABLED', true),
    'async_work_enabled' => (bool) env('MARS_ASYNC_WORK_ENABLED', true),
    'scheduler_work_enabled' => (bool) env('MARS_SCHEDULER_WORK_ENABLED', true),
    'recovery_retry_after_seconds' => max(30, (int) env('MARS_RECOVERY_RETRY_AFTER_SECONDS', 300)),
    'disabled_providers' => array_values(array_filter(array_map(
        static fn (string $provider): string => strtolower(trim($provider)),
        explode(',', (string) env('MARS_DISABLED_PROVIDERS', '')),
    ))),
    'backup' => [
        'offsite_required' => (bool) env('MARS_BACKUP_OFFSITE_REQUIRED', true),
        'offsite_target' => env('MARS_BACKUP_OFFSITE_TARGET'),
        'recovery_key' => env('MARS_BACKUP_RECOVERY_KEY'),
        'recovery_key_reference' => env('MARS_BACKUP_RECOVERY_KEY_REFERENCE'),
        'allow_legacy_app_key_decryption' => (bool) env('MARS_BACKUP_ALLOW_LEGACY_APP_KEY_DECRYPTION', true),
        'rpo_hours' => max(1, (int) env('MARS_BACKUP_RPO_HOURS', 24)),
        'rto_hours' => max(1, (int) env('MARS_BACKUP_RTO_HOURS', 4)),
        'restore_drill_max_age_days' => max(1, (int) env('MARS_RESTORE_DRILL_MAX_AGE_DAYS', 30)),
        'retention' => [
            'daily' => max(1, (int) env('MARS_BACKUP_RETENTION_DAILY', 14)),
            'weekly' => max(1, (int) env('MARS_BACKUP_RETENTION_WEEKLY', 8)),
            'monthly' => max(1, (int) env('MARS_BACKUP_RETENTION_MONTHLY', 12)),
        ],
    ],
];
