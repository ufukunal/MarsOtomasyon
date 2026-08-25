<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

final class WarehouseTransferReceipt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'transfer_id',
        'line_id',
        'source_type',
        'source_id',
        'effect_type',
        'quantity',
        'carrying_value',
        'receipt_movement_id',
        'received_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'carrying_value' => 'decimal:6',
            'received_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
