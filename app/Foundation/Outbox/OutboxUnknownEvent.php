<?php

namespace App\Foundation\Outbox;

use InvalidArgumentException;

final class OutboxUnknownEvent extends InvalidArgumentException
{
}
