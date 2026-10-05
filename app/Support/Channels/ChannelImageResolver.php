<?php

namespace App\Support\Channels;

use App\Models\Attachment;
use App\Models\Period\ChannelProductListing;
use App\Support\Period\PeriodContext;
use App\Support\Products\ProductImageResolver;
use Illuminate\Support\Facades\URL;

final class ChannelImageResolver
{
    public function __construct(private readonly ProductImageResolver $images) {}

    /** @return list<string> */
    public function urls(ChannelProductListing $listing, string $platformCollection): array
    {
        $listing->loadMissing('product');
        $collection = trim((string) ($listing->image_collection ?? '')) ?: $platformCollection;
        $attachments = $this->images->forCollection($listing->product, $collection);

        if ($attachments->isEmpty() && $collection !== $platformCollection) {
            $attachments = $this->images->forCollection($listing->product, $platformCollection);
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
