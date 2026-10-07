<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $direction
 * @property string $kind
 * @property string $instrument_no
 * @property int|null $contact_id
 * @property int|null $contact_transaction_id
 * @property int|null $endorsed_to_contact_id
 * @property int|null $bank_account_id
 * @property int|null $last_payroll_id
 * @property Carbon|null $issue_date
 * @property Carbon $due_date
 * @property string $currency
 * @property string $amount
 * @property string $status
 * @property int $version
 */
class Security extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'direction', 'kind', 'instrument_no', 'fingerprint', 'contact_id',
        'contact_transaction_id', 'endorsed_to_contact_id', 'bank_account_id', 'last_payroll_id', 'bank_name',
        'issue_date', 'due_date', 'currency', 'amount', 'status', 'notes',
        'created_by', 'created_by_name',
    ];

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Çek/senet fiziksel olarak silinemez; durum işlemi kullanılmalıdır.');
        });
    }

    protected function casts(): array
    {
        return [
            'contact_id' => 'integer',
            'contact_transaction_id' => 'integer',
            'endorsed_to_contact_id' => 'integer',
            'bank_account_id' => 'integer',
            'last_payroll_id' => 'integer',
            'issue_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:4',
            'version' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<ContactTransaction, $this> */
    public function contactTransaction(): BelongsTo
    {
        return $this->belongsTo(ContactTransaction::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function endorsedToContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'endorsed_to_contact_id');
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return BelongsTo<SecurityPayroll, $this> */
    public function lastPayroll(): BelongsTo
    {
        return $this->belongsTo(SecurityPayroll::class, 'last_payroll_id');
    }
}
