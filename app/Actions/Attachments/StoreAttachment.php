<?php

namespace App\Actions\Attachments;

use App\Contracts\HasAttachments as HasAttachmentsContract;
use App\Models\Attachment;
use App\Models\PeriodModel;
use App\Support\Security\SecureUploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class StoreAttachment
{
    public function handle(
        PeriodModel&HasAttachmentsContract $attachable,
        UploadedFile $file,
        ?string $collection = null,
        int $sortOrder = 0,
    ): Attachment {
        $validated = app(SecureUploadValidator::class)->validate($file);
        $mime = $validated['mime'];
        $originalExtension = $validated['extension'];
        $size = $validated['size'];

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
            $attachment = $attachable->attachments()->create([
                'disk' => $disk,
                'path' => $stored,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => $size,
                'collection' => $collection,
                'sort_order' => $sortOrder,
                'uploaded_by' => $actor?->getAuthIdentifier(),
                'uploaded_by_name' => $actor?->name,
            ]);

            if (! $attachment instanceof Attachment) {
                throw new RuntimeException('Ek kaydı beklenen model tipinde oluşturulamadı.');
            }

            return $attachment;
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($stored);

            throw $exception;
        }
    }
}
