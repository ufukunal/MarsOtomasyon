<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\PostingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class AccountTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'account_id',
        'posting_period_id',
        'posting_date',
        'currency_code',
        'signed_amount',
        'source_type',
        'source_id',
        'effect_type',
        'effect_fingerprint',
        'memo',
        'reversal_of_transaction_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'posting_date' => 'immutable_date',
            'signed_amount' => 'decimal:6',
            'created_at' => 'immutable_datetime',
        ];
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

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return BelongsTo<PostingPeriod, $this> */
    public function postingPeriod(): BelongsTo
    {
        return $this->belongsTo(PostingPeriod::class);
    }

    /** @return BelongsTo<AccountTransaction, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_transaction_id');
    }

    /** @return HasOne<AccountTransaction, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_transaction_id');
    }
}
