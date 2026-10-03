<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasAttachments
{
    public function attachments(): MorphMany;
}
