<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $channel_account_id
 * @property int $product_id
 * @property string|null $stock_mode
 * @property string|null $max_channel_quantity
 * @property string|null $withhold_quantity
 * @property string|null $fixed_quantity
 * @property string|null $manual_quantity
 * @property int|null $lead_time_days
 * @property string|null $price_override
 * @property array<string, mixed>|null $category_metadata
 * @property bool $is_active
 */
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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ChannelListingLocation, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(ChannelListingLocation::class);
    }
}
