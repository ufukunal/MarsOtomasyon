<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExchangeRate extends Model
{
    protected $fillable = [
        'company_id',
        'rate_date',
        'from_currency_code',
        'to_currency_code',
        'rate',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rate_date' => 'immutable_date',
            'rate' => 'decimal:10',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
