<?php

namespace App\Models\Audit;

use App\Support\Audit\AuditContext;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    public function getConnectionName()
    {
        return AuditContext::connection();
    }
}
