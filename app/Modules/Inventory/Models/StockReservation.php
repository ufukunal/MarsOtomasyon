<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\StockReservationStatus;
use Illuminate\Database\Eloquent\Model;

final class StockReservation extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'location_id',
        'quantity',
        'status',
        'reserve_source_type',
        'reserve_source_id',
        'reserve_effect_type',
        'release_source_type',
        'release_source_id',
        'release_effect_type',
        'consume_source_type',
        'consume_source_id',
        'consume_effect_type',
        'reserved_at',
        'released_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'reserved_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function statusEnum(): StockReservationStatus
    {
        return StockReservationStatus::from((string) $this->status);
    }
}
