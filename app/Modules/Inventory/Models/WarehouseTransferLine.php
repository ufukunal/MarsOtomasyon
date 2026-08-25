<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

final class WarehouseTransferLine extends Model
{
    protected $fillable = [
        'company_id',
        'transfer_id',
        'line_number',
        'product_id',
        'issued_quantity',
        'unit_cost',
        'issued_value',
        'received_quantity',
        'received_value',
        'issue_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'issued_value' => 'decimal:6',
            'received_quantity' => 'decimal:6',
            'received_value' => 'decimal:6',
            'in_transit_quantity' => 'decimal:6',
            'in_transit_value' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
