<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $product_id
 * @property int $location_id
 * @property string $quantity
 * @property string $document_type
 * @property int|null $document_id
 * @property int|null $document_line_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $released_at
 */
class StockReservation extends PeriodModel
{
    protected $fillable = [
        'product_id',
        'location_id',
        'quantity',
        'document_type',
        'document_id',
        'document_line_id',
        'status',
        'created_by',
        'created_by_name',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'document_id' => 'integer',
            'document_line_id' => 'integer',
            'version' => 'integer',
            'created_by' => 'integer',
            'released_at' => 'datetime',
        ];
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
