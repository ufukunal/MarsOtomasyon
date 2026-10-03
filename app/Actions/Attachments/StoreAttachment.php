<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;
use App\Models\PeriodModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class StoreAttachment
{
    public function handle(
        PeriodModel $attachable,
        UploadedFile $file,
        ?string $collection = null,
        int $sortOrder = 0,
    ): Attachment {
        $mime = (string) $file->getMimeType();
        $allowed = config("attachments.mime_extensions.{$mime}");

        if (! is_array($allowed) || $allowed === []) {
            throw ValidationException::withMessages([
                'file' => 'Dosya türüne izin verilmiyor.',
            ]);
        }

        if (($file->getSize() ?: 0) > (int) config('attachments.max_size')) {
            throw ValidationException::withMessages([
                'file' => 'Dosya boyutu 25 MB sınırını aşıyor.',
            ]);
        }

        $originalExtension = Str::lower($file->getClientOriginalExtension());

        if ($originalExtension === 'svg' || ! in_array($originalExtension, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'Dosya uzantısı içerik türüyle uyumlu değil.',
            ]);
        }

        $disk = (string) config('attachments.disk', 'attachments');
        $directory = now()->format('Y/m');
        $filename = Str::uuid()->toString().'.'.$originalExtension;

        $stored = Storage::disk($disk)->putFileAs(
            $directory,
            $file,
            $filename,
        );

        if ($stored === false) {
            throw new RuntimeException('Dosya diske yazılamadı.');
        }

        $actor = auth()->user();

        try {
            return $attachable->attachments()->create([
                'disk' => $disk,
                'path' => $stored,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => (int) ($file->getSize() ?: 0),
                'collection' => $collection,
                'sort_order' => $sortOrder,
                'uploaded_by' => $actor?->getAuthIdentifier(),
                'uploaded_by_name' => $actor?->name,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($stored);

            throw $exception;
        }
    }
}
