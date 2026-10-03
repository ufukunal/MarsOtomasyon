<?php

namespace App\Contracts;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasAttachments
{
    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<Attachment, $this> */
    public function attachments(): MorphMany;
}
