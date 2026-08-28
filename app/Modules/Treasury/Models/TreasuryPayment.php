<?php

namespace App\Modules\Treasury\Models;

use Illuminate\Database\Eloquent\Model;

final class TreasuryPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payment_date' => 'immutable_date',
            'amount' => 'decimal:6',
            'finalized_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}
