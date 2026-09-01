<?php

return [
    'recovery_mode' => (bool) env('MARS_RECOVERY_MODE', false),
    'outbound_providers_enabled' => (bool) env('MARS_OUTBOUND_PROVIDERS_ENABLED', true),
    'async_work_enabled' => (bool) env('MARS_ASYNC_WORK_ENABLED', true),
    'scheduler_work_enabled' => (bool) env('MARS_SCHEDULER_WORK_ENABLED', true),
    'recovery_retry_after_seconds' => max(30, (int) env('MARS_RECOVERY_RETRY_AFTER_SECONDS', 300)),
    'disabled_providers' => array_values(array_filter(array_map(
        static fn (string $provider): string => strtolower(trim($provider)),
        explode(',', (string) env('MARS_DISABLED_PROVIDERS', '')),
    ))),
];
