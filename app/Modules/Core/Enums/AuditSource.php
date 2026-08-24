<?php

namespace App\Modules\Core\Enums;

enum AuditSource: string
{
    case Web = 'web';
    case Api = 'api';
    case Job = 'job';
    case Console = 'console';
    case System = 'system';
}
