<?php

namespace App\Contracts;

use App\Models\Attachment;
use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasAttachments
{
    /** @return MorphMany<Attachment, covariant PeriodModel> */
    public function attachments(): MorphMany;
}
