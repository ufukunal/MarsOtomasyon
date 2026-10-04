<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 */
class PriceListItem extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['price_list_id', 'product_id', 'price', 'valid_from', 'valid_to'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<PriceList, $this> */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
