<?php

namespace App\Foundation\Logging;

use Illuminate\Support\Facades\Context;
use InvalidArgumentException;

final class OperationLogContext
{
    public function job(string $jobId): void
    {
        $this->put('job_id', $jobId);
    }

    public function outbox(string $eventId): void
    {
        $this->put('outbox_message_id', $eventId);
    }

    private function put(string $key, string $value): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $value) !== 1) {
            throw new InvalidArgumentException('Operational log context ID is invalid.');
        }

        Context::add($key, $value);
    }
}
