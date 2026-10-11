<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportSlipItem extends PeriodModel
{
    protected $fillable = ['transport_slip_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    /** @return BelongsTo<TransportSlip, $this> */
    public function slip(): BelongsTo
    {
        return $this->belongsTo(TransportSlip::class, 'transport_slip_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
