<?php

namespace App\Foundation\Outbox;

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Leased = 'leased';
    case Completed = 'completed';
    case Failed = 'failed';
}
