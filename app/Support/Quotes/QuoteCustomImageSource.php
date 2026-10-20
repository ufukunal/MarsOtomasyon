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
    /** @return array{bytes:string,mime:string,size:int}|null Historical render, scoped to this image. */
    public function readVersion(Attachment $attachment, int $index): ?array
    {
        if ($index === -1) {
            return $this->read($attachment, true);
        }
        if ($index < 0 || $index >= 30) {
            return null;
        }
        $version = $attachment->editedVersions()->orderBy('id')->skip($index)->first();

        return $version === null ? null : $this->readFile($version);
    }

    /** @return array{bytes:string,mime:string,size:int}|null */
    private function readFile(Attachment|\App\Models\QuoteCustomImageEdit $file): ?array
    {
        if (! in_array($file->mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || (int) $file->size < 1 || (int) $file->size > 5 * 1024 * 1024) {
            return null;
        }
        try {
            $bytes = Storage::disk($file->disk)->get($file->path);
            $length = strlen($bytes);
            if ($length < 1 || $length > 5 * 1024 * 1024
                || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $file->mime) {
                return null;
            }
            return ['bytes' => $bytes, 'mime' => $file->mime, 'size' => $length];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{bytes:string,mime:string,size:int}|null */
    public function read(Attachment $attachment, bool $original = false): ?array
    {
        $versions = $original ? [] : [$attachment->editedVersions()->latest('id')->first()];
        $versions[] = $attachment;

        foreach ($versions as $file) {
            if ($file === null) {
                continue;
            }
            $data = $this->readFile($file);
            if ($data !== null) {
                return $data;
            }
        }

        return null;
    }
}
