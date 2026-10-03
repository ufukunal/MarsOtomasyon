<?php

namespace App\Observers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class AttachmentObserver
{
    public function deleted(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
    }
}
