<?php

namespace App\Actions\Quotes;

use App\Models\Attachment;
use App\Models\QuoteCustomImageEdit;
use App\Support\Period\PeriodContext;
use App\Support\Security\SecureUploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SaveCustomQuoteImageEdit
{
    public function handle(Attachment $image, UploadedFile $upload, array $instructions = []): QuoteCustomImageEdit
    {
        PeriodContext::ensureWritable();
        $validated = app(SecureUploadValidator::class)->validate($upload);
        if (! in_array($validated['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || $validated['size'] < 1 || $validated['size'] > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['customImageEditUpload' => 'Yalnızca 5 MB altındaki JPG, PNG ve WebP resimler kaydedilebilir.']);
        }

        $dimensions = @getimagesize($upload->getRealPath());
        if (! is_array($dimensions) || $dimensions[0] < 1 || $dimensions[1] < 1
            || $dimensions[0] > 3200 || $dimensions[1] > 3200
            || $dimensions[0] * $dimensions[1] > 10000000
            || $dimensions['mime'] !== $validated['mime']) {
            throw ValidationException::withMessages(['customImageEditUpload' => 'Görsel ölçüleri geçersiz veya çok büyük.']);
        }

        // The browser's instruction list is auditing metadata, not executable code.
        $safeSteps = collect($instructions)->take(40)->filter(
            static fn ($instruction): bool => is_string($instruction)
        )->map(
            static fn (string $instruction): string => mb_substr($instruction, 0, 80)
        )->values()->all();

        $disk = (string) config('attachments.disk', 'attachments');
        $directory = now()->format('Y/m').'/quote-image-edits';
        $path = Storage::disk($disk)->putFileAs(
            $directory, $upload, (string) Str::uuid().'.'.$validated['extension']
        );
        if ($path === false) {
            throw new RuntimeException('Düzenlenen görsel kaydedilemedi.');
        }

        try {
            return DB::connection('period')->transaction(function () use ($image, $validated, $safeSteps, $disk, $path): QuoteCustomImageEdit {
                $locked = Attachment::query()->whereKey($image->id)->lockForUpdate()->firstOrFail();
                if ($locked->editedVersions()->count() >= 30) {
                    throw ValidationException::withMessages([
                        'customImageEditUpload' => 'Görsel başına en fazla 30 düzenleme sürümü oluşturulabilir.',
                    ]);
                }
                return $locked->editedVersions()->create([
                    'disk' => $disk,
                    'path' => $path,
                    'mime' => $validated['mime'],
                    'size' => $validated['size'],
                    'instructions' => $safeSteps,
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }
}
