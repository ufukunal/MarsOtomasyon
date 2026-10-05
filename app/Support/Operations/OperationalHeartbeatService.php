<?php

namespace App\Support\Operations;

use App\Models\OperationalHeartbeat;

final class OperationalHeartbeatService
{
    /** @param array<string,mixed> $metadata */
    public function touch(string $serviceKey, array $metadata = []): void
    {
        OperationalHeartbeat::query()->updateOrCreate(
            ['service_key' => $serviceKey],
            ['last_seen_at' => now(), 'metadata' => $metadata],
        );
    }
}
