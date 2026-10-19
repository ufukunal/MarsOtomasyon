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
                $target->attachments()->create([
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
            }
        } catch (\Throwable $exception) {
            foreach ($copied as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $exception;
        }
    }
}
