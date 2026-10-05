<?php

namespace App\Support\Channels;

use App\Models\Attachment;
use App\Models\Period\ChannelProductListing;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\URL;

final class ChannelImageResolver
{
    /** @return list<string> */
    public function urls(ChannelProductListing $listing, string $platformCollection): array
    {
        $listing->loadMissing('product');

        $collections = collect([
            trim((string) ($listing->image_collection ?? '')),
            trim($platformCollection),
            trim((string) config('product_images.fallback', 'Ortak')),
        ])
            ->filter(fn (string $collection): bool => $collection !== '')
            ->unique()
            ->values();

        $attachments = collect();

        foreach ($collections as $collection) {
            $attachments = $listing->product->attachments()
                ->where('collection', $collection)
                ->orderBy('sort_order')
                ->get();

            if ($attachments->isNotEmpty()) {
                break;
            }
        }

        return $attachments
            ->filter(fn (Attachment $attachment): bool => str_starts_with((string) $attachment->mime, 'image/'))
            ->sortBy('sort_order')
            ->values()
            ->map(fn (Attachment $attachment): string => URL::signedRoute('channels.asset', [
                'company' => PeriodContext::companyId(),
                'period' => PeriodContext::periodId(),
                'product' => $listing->product_id,
                'attachment' => $attachment->id,
            ]))
            ->all();
    }
}
