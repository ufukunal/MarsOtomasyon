<?php

namespace App\Contracts;

use App\Models\Attachment;
use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @template TModel of PeriodModel
 */
interface HasAttachments
{
    /** @return MorphMany<Attachment, TModel> */
    public function attachments(): MorphMany;
}
