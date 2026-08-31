<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Accounts\Enums\AccountAddressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class AccountAddress extends Model
{
    protected $fillable = [
        'company_id', 'account_id', 'type', 'label', 'recipient_name', 'line1', 'line2', 'district', 'city',
        'postal_code', 'country_code', 'is_default',
    ];

    protected static function booted(): void
    {
        self::creating(function (AccountAddress $address): void {
            if (! is_string($address->public_id) || $address->public_id === '') {
                $address->public_id = (string) Str::ulid();
            }
        });

        self::updating(function (AccountAddress $address): void {
            if ($address->isDirty('public_id')) {
                throw new LogicException('Account address public_id is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => AccountAddressType::class,
            'is_default' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function typeEnum(): AccountAddressType
    {
        $raw = $this->getRawOriginal('type');
        if (! is_string($raw)) {
            throw new LogicException('Persisted account address type must be a string.');
        }

        return AccountAddressType::tryFrom($raw)
            ?? throw new LogicException('Persisted account address type is invalid.');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
