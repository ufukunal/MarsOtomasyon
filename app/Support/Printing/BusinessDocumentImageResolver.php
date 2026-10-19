<?php

namespace App\Support\Printing;

use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
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

    /**
     * Source-line ancestry preserves a quoted custom item's cover image through
     * quote -> order -> dispatch -> invoice without stock product creation.
     *
     * @return array<string,string> Custom code => embedded image data URI
     */
    public function forCustomDocument(Document $document): array
    {
        $document->loadMissing('lines');
        $result = [];
        $budget = 15 * 1024 * 1024;

        foreach ($document->lines as $line) {
            if ($line->line_kind !== 'custom') {
                continue;
            }
            $code = (string) ($line->configuration['custom_code'] ?? '');
            if (preg_match('/^OZ-[0-9]{4}-[0-9]{6}$/D', $code) !== 1 || isset($result[$code])) {
                continue;
            }

            $current = $line;
            $seen = [];
            for ($depth = 0; $current !== null && $depth < 12; $depth++) {
                if (isset($seen[$current->id])) {
                    break;
                }
                $seen[$current->id] = true;
                $owner = $current->document;
                if ($owner === null) {
                    break;
                }
                $image = $owner->attachments()
                    ->where('collection', 'quote_custom_'.$code)
                    ->whereIn('mime', ['image/png', 'image/jpeg', 'image/webp'])
                    ->orderBy('sort_order')->first();

                if ($image !== null && (int) $image->size <= 5 * 1024 * 1024) {
                    try {
                        $bytes = Storage::disk($image->disk)->get($image->path);
                        $length = strlen($bytes);
                        if ($length > 0 && $length <= 5 * 1024 * 1024 && $length <= $budget) {
                            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                            if ($mime === $image->mime) {
                                $result[$code] = 'data:'.$mime.';base64,'.base64_encode($bytes);
                                $budget -= $length;
                                break;
                            }
                        }
                    } catch (\Throwable) {
                        // Missing media must not prevent an otherwise valid PDF.
                    }
                }

                $current->loadMissing('sourceLine.document');
                $parent = $current->sourceLine;
                if ($parent !== null && ($parent->configuration['custom_code'] ?? '') !== $code) {
                    break;
                }
                $current = $parent;
            }
        }

        return $result;
    }

}
