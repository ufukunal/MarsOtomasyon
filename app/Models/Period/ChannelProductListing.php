<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelProductListing extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'channel_account_id',
        'product_id',
        'external_product_id',
        'external_listing_id',
        'external_sku',
        'stock_mode',
        'max_channel_quantity',
        'withhold_quantity',
        'fixed_quantity',
        'manual_quantity',
        'lead_time_days',
        'price_override',
        'title_override',
        'description_override',
        'image_collection',
        'category_metadata',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'channel_account_id' => 'integer',
            'product_id' => 'integer',
            'max_channel_quantity' => 'decimal:3',
            'withhold_quantity' => 'decimal:3',
            'fixed_quantity' => 'decimal:3',
            'manual_quantity' => 'decimal:3',
            'lead_time_days' => 'integer',
            'price_override' => 'decimal:4',
            'category_metadata' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ChannelListingLocation::class);
    }
}
