<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $number
 * @property string $action
 * @property int|null $contact_id
 * @property int|null $bank_account_id
 * @property int|null $reversal_of_id
 * @property Carbon $payroll_date
 * @property string $currency
 * @property string $total_amount
 * @property list<int> $security_ids
 * @property array<string,mixed>|null $state_snapshot
 * @property string $status
 */
class SecurityPayroll extends PeriodModel
{
    protected $fillable = [
        'number', 'action', 'contact_id', 'bank_account_id', 'reversal_of_id', 'payroll_date',
        'currency', 'total_amount', 'security_ids', 'state_snapshot', 'status', 'notes',
        'created_by', 'created_by_name',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Kesinleşmiş çek/senet bordrosu yerinde değiştirilemez.');
        });

        static::deleting(function (): never {
            throw new LogicException('Çek/senet bordrosu fiziksel olarak silinemez.');
        });
    }

    protected function casts(): array
    {
        return [
            'contact_id' => 'integer',
            'bank_account_id' => 'integer',
            'reversal_of_id' => 'integer',
            'payroll_date' => 'date',
            'total_amount' => 'decimal:4',
            'security_ids' => 'array',
            'state_snapshot' => 'array',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<SecurityPayroll, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
