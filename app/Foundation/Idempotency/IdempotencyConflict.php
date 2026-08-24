<?php

namespace App\Foundation\Idempotency;

use RuntimeException;

final class IdempotencyConflict extends RuntimeException
{
}
