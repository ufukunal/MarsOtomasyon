<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelListingLocation extends PeriodModel
{
    protected $fillable = [
        'channel_product_listing_id',
        'location_id',
    ];

    protected function casts(): array
    {
        return [
            'channel_product_listing_id' => 'integer',
            'location_id' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ChannelProductListing::class, 'channel_product_listing_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
