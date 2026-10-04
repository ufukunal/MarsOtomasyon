<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends PeriodModel
{
    protected $fillable = [
        'cash_account_id', 'document_id', 'contact_id', 'movement_date', 'direction',
        'amount', 'description', 'created_by', 'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'cash_account_id' => 'integer', 'document_id' => 'integer', 'contact_id' => 'integer',
            'movement_date' => 'date', 'amount' => 'decimal:4', 'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<CashAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }
}
