<?php

namespace App\Support\Printing;

use App\Models\Period\Document;
use Illuminate\Support\Facades\Storage;

/**
 * Embed product thumbnails instead of requesting authenticated image URLs
 * from a headless PDF browser. Read only owned product attachments.
 */
final class BusinessDocumentImageResolver
{
    /** @return array<int, string> Product ID => safe, self-contained image data URI */
    public function forDocument(Document $document): array
    {
        $document->loadMissing('lines.product.attachments');
        $result = [];
        $seen = [];
        $bytesUsed = 0;
        $maxBytes = 12 * 1024 * 1024;
        $mimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        foreach ($document->lines as $line) {
            $product = $line->product;
            if ($product === null || isset($seen[$product->id])) {
                continue;
            }
            $seen[$product->id] = true;

            // Match ProductPolicy::view to avoid bypassing product-image access.
            if (! auth()->user()?->can('view', $product)) {
                continue;
            }

            $images = $product->attachments->filter(
                static fn ($item): bool => in_array($item->mime, $mimeTypes, true)
                    && (int) $item->size > 0 && (int) $item->size <= 1536000
            )->sortBy('sort_order');

            $preferred = (string) config('product_images.fallback', 'Ortak');
            $attachment = $images->firstWhere('collection', $preferred) ?? $images->first();
            if ($attachment === null) {
                continue;
            }

            try {
                // Never embed a remote URL, SVG, HTML or an unverified MIME.
                $bytes = Storage::disk($attachment->disk)->get($attachment->path);
                $length = strlen($bytes);
                if ($length === 0 || $length > 1536000 || $bytesUsed + $length > $maxBytes) {
                    continue;
                }

                $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                if ($actualMime !== $attachment->mime) {
                    continue;
                }

                $result[$product->id] = 'data:'.$actualMime.';base64,'.base64_encode($bytes);
                $bytesUsed += $length;
            } catch (\Throwable) {
                // An unavailable product photo never breaks a business document.
                continue;
            }
        }

        return $result;
    }
}
