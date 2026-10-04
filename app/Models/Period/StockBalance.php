<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends PeriodModel
{
    protected $fillable = [
        'product_id',
        'location_id',
        'quantity',
        'reserved',
        'consignment_reserved',
        'quarantine',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved' => 'decimal:3',
            'consignment_reserved' => 'decimal:3',
            'quarantine' => 'decimal:3',
        ];
    }

    public function available(): string
    {
        $value = bcsub((string) $this->quantity, (string) $this->reserved, 3);
        $value = bcsub($value, (string) $this->consignment_reserved, 3);

        return bcsub($value, (string) $this->quarantine, 3);
    }

    public function getAvailableAttribute(): string
    {
        return $this->available();
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
