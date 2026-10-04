<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $bank_account_id
 * @property int|null $document_id
 * @property int|null $contact_id
 * @property Carbon $movement_date
 * @property string $direction
 * @property string $movement_type
 * @property string $amount
 * @property string $origin
 * @property string|null $group_key
 * @property int|null $reversal_of_id
 * @property string|null $statement_fingerprint
 * @property int|null $reconciled_movement_id
 * @property Carbon|null $reconciled_at
 * @property array<string,mixed>|null $metadata
 */
class BankMovement extends PeriodModel
{
    protected static function booted(): void
    {
        static::updating(function (self $movement): void {
            if ($movement->getOriginal('origin') !== 'statement') {
                throw new LogicException('Kesinleşmiş banka defter hareketi yerinde değiştirilemez.');
            }

            $allowed = [
                'reconciled_movement_id',
                'reconciled_at',
                'reconciled_by',
                'reconciled_by_name',
                'updated_at',
            ];

            foreach (array_keys($movement->getDirty()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    throw new LogicException('Ekstre hareketinde yalnız mutabakat alanları güncellenebilir.');
                }
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Banka hareketi fiziksel olarak silinemez.');
        });
    }

    protected $fillable = [
        'bank_account_id', 'document_id', 'contact_id', 'movement_date', 'direction',
        'movement_type', 'amount', 'origin', 'reference', 'group_key', 'reversal_of_id',
        'statement_fingerprint', 'statement_value_date', 'statement_description', 'statement_balance',
        'reconciled_movement_id', 'reconciled_at', 'reconciled_by', 'reconciled_by_name',
        'imported_at', 'description', 'metadata', 'created_by', 'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'bank_account_id' => 'integer',
            'document_id' => 'integer',
            'contact_id' => 'integer',
            'movement_date' => 'date',
            'amount' => 'decimal:4',
            'reversal_of_id' => 'integer',
            'statement_value_date' => 'date',
            'statement_balance' => 'decimal:4',
            'reconciled_movement_id' => 'integer',
            'reconciled_at' => 'datetime',
            'reconciled_by' => 'integer',
            'imported_at' => 'datetime',
            'metadata' => 'array',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /** @return BelongsTo<BankMovement, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return BelongsTo<BankMovement, $this> */
    public function reconciledMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reconciled_movement_id');
    }
}
