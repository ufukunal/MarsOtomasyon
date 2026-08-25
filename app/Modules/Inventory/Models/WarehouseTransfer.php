<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

final class WarehouseTransfer extends Model
{
    protected $fillable = [
        'company_id',
        'source_warehouse_id',
        'source_location_id',
        'destination_warehouse_id',
        'destination_location_id',
        'status',
        'issue_source_type',
        'issue_source_id',
        'issue_effect_type',
        'issued_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
