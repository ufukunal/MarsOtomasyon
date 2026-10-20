<?php

namespace App\Support\Quotes;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

/**
 * Private image access through a document-owned attachment; never a supplied URL.
 * Originals are immutable. The newest stored edit is the active rendering.
 */
final class QuoteCustomImageSource
{
    /** @return array{bytes:string,mime:string,size:int}|null */
    public function read(Attachment $attachment, bool $original = false): ?array
    {
        $versions = $original ? [] : [$attachment->editedVersions()->latest('id')->first()];
        $versions[] = $attachment;

        foreach ($versions as $file) {
            if ($file === null || ! in_array($file->mime, ['image/jpeg', 'image/png', 'image/webp'], true)
                || (int) $file->size < 1 || (int) $file->size > 5 * 1024 * 1024) {
                continue;
            }

            try {
                $bytes = Storage::disk($file->disk)->get($file->path);
                $length = strlen($bytes);
                if ($length === 0 || $length > 5 * 1024 * 1024) {
                    continue;
                }
                if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $file->mime) {
                    continue;
                }

                return ['bytes' => $bytes, 'mime' => $file->mime, 'size' => $length];
            } catch (\Throwable) {
                // Missing edited files fall back to the original; never to a remote URL.
            }
        }

        return null;
    }
}
