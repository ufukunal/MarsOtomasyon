<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
