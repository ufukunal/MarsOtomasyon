<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Accounts\Enums\AccountAddressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccountAddress extends Model
{
    protected $fillable = [
        'company_id',
        'account_id',
        'type',
        'label',
        'recipient_name',
        'line1',
        'line2',
        'district',
        'city',
        'postal_code',
        'country_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountAddressType::class,
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
}
