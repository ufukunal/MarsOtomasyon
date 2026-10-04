<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferLine extends PeriodModel
{
    protected $fillable = [
        'transfer_id',
        'product_id',
        'quantity',
        'received_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
        ];
    }

    public function remainingQuantity(): string
    {
        return bcsub((string) $this->quantity, (string) $this->received_quantity, 3);
    }

    /** @return BelongsTo<Transfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
