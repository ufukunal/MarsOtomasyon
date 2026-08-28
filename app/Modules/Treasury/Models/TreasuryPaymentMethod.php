<?php

namespace App\Modules\Treasury\Models;

use Illuminate\Database\Eloquent\Model;

final class TreasuryPaymentMethod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
