<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $cash_account_id
 * @property int|null $document_id
 * @property int|null $contact_id
 * @property Carbon $movement_date
 * @property string $direction
 * @property string $movement_type
 * @property string $amount
 * @property string|null $reference
 * @property string|null $group_key
 * @property int|null $reversal_of_id
 * @property array<string,mixed>|null $metadata
 */
class CashMovement extends PeriodModel
{
    protected $fillable = [
        'cash_account_id', 'document_id', 'contact_id', 'movement_date', 'direction',
        'movement_type', 'amount', 'reference', 'group_key', 'reversal_of_id',
        'description', 'metadata', 'created_by', 'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'cash_account_id' => 'integer',
            'document_id' => 'integer',
            'contact_id' => 'integer',
            'movement_date' => 'date',
            'amount' => 'decimal:4',
            'reversal_of_id' => 'integer',
            'metadata' => 'array',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<CashAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    /** @return BelongsTo<CashMovement, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
