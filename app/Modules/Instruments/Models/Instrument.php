<?php

namespace App\Modules\Instruments\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountTransaction;
use App\Modules\Core\Models\Company;
use App\Modules\Treasury\Models\TreasuryAccount;
use App\Modules\Treasury\Models\TreasuryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Instrument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6',
            'issue_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'registered_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
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

    /** @return BelongsTo<Account, $this> */
    public function currentHolderAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'current_holder_account_id');
    }

    /** @return BelongsTo<TreasuryAccount, $this> */
    public function currentTreasuryAccount(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class, 'current_treasury_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function endorsedToAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'endorsed_to_account_id');
    }

    /** @return BelongsTo<AccountTransaction, $this> */
    public function deliveryAccountTransaction(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'delivery_account_transaction_id');
    }

    /** @return BelongsTo<TreasuryMovement, $this> */
    public function settlementTreasuryMovement(): BelongsTo
    {
        return $this->belongsTo(TreasuryMovement::class, 'settlement_treasury_movement_id');
    }

    /** @return HasMany<InstrumentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(InstrumentEvent::class)->orderBy('id');
    }
}
