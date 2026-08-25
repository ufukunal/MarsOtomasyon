<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Accounts\Enums\AccountContactKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class AccountContact extends Model
{
    protected $fillable = [
        'company_id',
        'account_id',
        'kind',
        'label',
        'value',
        'normalized_value',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AccountContactKind::class,
            'is_primary' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function kindEnum(): AccountContactKind
    {
        $raw = $this->getRawOriginal('kind');
        if (! is_string($raw)) {
            throw new LogicException('Persisted account contact kind must be a string.');
        }

        return AccountContactKind::tryFrom($raw)
            ?? throw new LogicException('Persisted account contact kind is invalid.');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
