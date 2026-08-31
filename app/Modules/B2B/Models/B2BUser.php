<?php

namespace App\Modules\B2B\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountB2BPolicy;
use App\Modules\B2B\Enums\B2BUserStatus;
use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use LogicException;

final class B2BUser extends Authenticatable
{
    protected $table = 'b2b_users';

    protected $fillable = [
        'company_id',
        'account_id',
        'name',
        'email',
        'password',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        self::saving(function (B2BUser $user): void {
            $email = $user->getAttribute('email');
            if (is_string($email)) {
                $user->setAttribute('email', mb_strtolower(trim($email)));
            }
        });

        self::creating(function (B2BUser $user): void {
            $publicId = $user->getAttribute('public_id');
            if (is_string($publicId) === false || $publicId === '') {
                $user->setAttribute('public_id', (string) Str::ulid());
            }
        });

        self::updating(function (B2BUser $user): void {
            if ($user->isDirty('public_id')) {
                throw new LogicException('B2B user public_id is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => B2BUserStatus::class,
            'last_login_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function statusEnum(): B2BUserStatus
    {
        $raw = $this->getRawOriginal('status');
        if (is_string($raw) === false) {
            throw new LogicException('Persisted B2B user status must be a string.');
        }

        return B2BUserStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted B2B user status is invalid.');
    }

    public function isActive(): bool
    {
        return $this->statusEnum() === B2BUserStatus::Active;
    }

    public function canAccessPortal(): bool
    {
        if ($this->isActive() === false) {
            return false;
        }

        $company = $this->company()->first();
        $account = $this->account()->first();

        if (($company instanceof Company) === false) {
            return false;
        }

        if ($company->isActive() === false) {
            return false;
        }

        if (($account instanceof Account) === false) {
            return false;
        }

        if ($account->isActive() === false) {
            return false;
        }

        return AccountB2BPolicy::query()
            ->where('company_id', $this->getAttribute('company_id'))
            ->where('account_id', $this->getAttribute('account_id'))
            ->where('is_enabled', true)
            ->exists();
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
