<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Core\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccountBankAccount extends Model
{
    protected $fillable = [
        'company_id',
        'account_id',
        'bank_name',
        'branch_name',
        'account_holder',
        'iban',
        'account_number',
        'swift_code',
        'currency_code',
        'is_default',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
