<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ContactTransfer extends PeriodModel
{
    protected $fillable = [
        'number', 'source_contact_id', 'target_contact_id',
        'source_transaction_id', 'target_transaction_id',
        'balance_type', 'transfer_date', 'amount', 'currency',
        'reason', 'reference', 'overdraft_approved', 'status', 'reversal_of_id',
        'created_by', 'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'source_contact_id' => 'integer',
            'target_contact_id' => 'integer',
            'source_transaction_id' => 'integer',
            'target_transaction_id' => 'integer',
            'reversal_of_id' => 'integer',
            'amount' => 'decimal:4',
            'overdraft_approved' => 'boolean',
            'transfer_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Kesinleşmiş cari virman yerinde değiştirilemez.');
        });
        self::deleting(function (): never {
            throw new LogicException('Cari virman silinemez; ters kayıt kullanılmalıdır.');
        });
    }

    /** @return BelongsTo<Contact, $this> */
    public function sourceContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'source_contact_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function targetContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'target_contact_id');
    }
}
