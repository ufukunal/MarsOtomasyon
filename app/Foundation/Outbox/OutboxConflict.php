<?php

namespace App\Foundation\Outbox;

use RuntimeException;

final class OutboxConflict extends RuntimeException {}
