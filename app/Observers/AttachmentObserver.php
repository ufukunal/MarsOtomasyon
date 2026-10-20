<?php

namespace App\Observers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class AttachmentObserver
{
    public function deleting(Attachment $attachment): void
    {
        // PostgreSQL cascades child rows; cache file locations before deletion.
        $attachment->setRelation('deletedQuoteImageVersions', $attachment->editedVersions()->get());
    }

    public function deleted(Attachment $attachment): void
    {
        foreach ($attachment->getRelation('deletedQuoteImageVersions') ?? [] as $version) {
            Storage::disk($version->disk)->delete($version->path);
        }
        Storage::disk($attachment->disk)->delete($attachment->path);
    }
}
