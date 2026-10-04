<?php

namespace App\Models\Audit;

use App\Models\PeriodModel;
use App\Support\Audit\AuditContext;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    public function getConnectionName()
    {
        $subjectType = $this->getAttribute('subject_type');

        if (is_string($subjectType)
            && class_exists($subjectType)
            && is_subclass_of($subjectType, PeriodModel::class)) {
            return 'period';
        }

        return AuditContext::connection();
    }
}
