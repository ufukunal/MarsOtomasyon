<?php

namespace App\Foundation\Idempotency;

enum IdempotencyStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
