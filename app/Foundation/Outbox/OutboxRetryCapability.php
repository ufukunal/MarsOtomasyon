<?php

namespace App\Foundation\Outbox;

enum OutboxRetryCapability: string
{
    case SafeRetry = 'SAFE_RETRY';
    case IdempotentWithKey = 'IDEMPOTENT_WITH_KEY';
    case QueryBeforeRetry = 'QUERY_BEFORE_RETRY';
    case NeverAutoRetry = 'NEVER_AUTO_RETRY';
}
