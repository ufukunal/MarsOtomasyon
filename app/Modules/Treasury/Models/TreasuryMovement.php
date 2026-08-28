<?php

namespace App\Modules\Treasury\Models;

use Illuminate\Database\Eloquent\Model;

final class TreasuryMovement extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'posting_date' => 'date:Y-m-d',
            'signed_amount' => 'decimal:6',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
