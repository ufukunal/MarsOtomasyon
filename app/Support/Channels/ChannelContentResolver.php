<?php

namespace App\Support\Channels;

use App\Models\Period\ChannelProductListing;

final class ChannelContentResolver
{
    /** @return array{title:string,description:string} */
    public function resolve(ChannelProductListing $listing): array
    {
        $listing->loadMissing('product');

        $title = trim((string) ($listing->title_override ?: $listing->product->name));
        $description = trim((string) ($listing->description_override ?: $listing->product->description));

        return [
            'title' => $title,
            'description' => $description !== '' ? $description : $title,
        ];
    }
}
