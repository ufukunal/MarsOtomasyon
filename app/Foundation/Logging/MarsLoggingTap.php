<?php

namespace App\Foundation\Logging;

use Illuminate\Log\Logger;

final class MarsLoggingTap
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new SensitiveDataProcessor(new SensitiveDataRedactor));
    }
}
