<?php

namespace App\Support\Quotes;

use App\Models\Period\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Copy media bytes per revision: deleting one image never removes an older revision's file. */
final class CopyCustomQuoteImages
{
    public function handle(Document $source, Document $target): void
    {
        $codes = $target->lines()->where('line_kind', 'custom')->get()
            ->map(static fn ($line): string => (string) ($line->configuration['custom_code'] ?? ''))
            ->filter()->map(static fn ($code): string => 'quote_custom_'.$code)->all();

        $copied = [];
        try {
            foreach ($source->attachments()->whereIn('collection', $codes)->get() as $image) {
                if (! in_array($image->mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    continue;
                }
                $newPath = 'quote-custom-copies/'.Str::uuid().'.'.match ($image->mime) {
                    'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg'
                };
                if (! Storage::disk($image->disk)->copy($image->path, $newPath)) {
                    throw new RuntimeException('Teklif revizyon görseli kopyalanamadı.');
                }
                $copied[] = [$image->disk, $newPath];
                $newOriginal = $target->attachments()->create([
                    'disk' => $image->disk,
                    'path' => $newPath,
                    'original_name' => $image->original_name,
                    'mime' => $image->mime,
                    'size' => $image->size,
                    'collection' => $image->collection,
                    'sort_order' => $image->sort_order,
                    'uploaded_by' => auth()->id(),
                    'uploaded_by_name' => auth()->user()?->name,
                ]);

                // Each revision owns independent immutable files and edit history.
                foreach ($image->editedVersions()->orderBy('id')->get() as $version) {
                    $newEditedPath = 'quote-custom-copies/'.Str::uuid().'.'.match ($version->mime) {
                        'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg',
                    };
                    if (! Storage::disk($version->disk)->copy($version->path, $newEditedPath)) {
                        throw new RuntimeException('Teklif görsel düzenleme geçmişi kopyalanamadı.');
                    }
                    $copied[] = [$version->disk, $newEditedPath];
                    $newOriginal->editedVersions()->create([
                        'disk' => $version->disk,
                        'path' => $newEditedPath,
                        'mime' => $version->mime,
                        'size' => $version->size,
                        'instructions' => $version->instructions,
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            foreach ($copied as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $exception;
        }
    }
}
