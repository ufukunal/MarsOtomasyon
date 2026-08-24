<?php

namespace App\Foundation\Outbox;

enum OutboxSemantic: string
{
    case ImmutableEventSnapshot = 'IMMUTABLE_EVENT_SNAPSHOT';
    case CurrentDesiredState = 'CURRENT_DESIRED_STATE';
}
