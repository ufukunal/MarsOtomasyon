<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $contact_id
 * @property int|null $document_id
 * @property string $transaction_type
 * @property string $direction
 * @property Carbon $transaction_date
 * @property Carbon|null $due_date
 * @property string $amount
 * @property string $currency
 * @property int|null $reversal_of_id
 */
class ContactTransaction extends PeriodModel
{
    protected $fillable = [
        'contact_id', 'document_id', 'transaction_type', 'direction', 'transaction_date',
        'due_date', 'amount', 'currency', 'reversal_of_id', 'description',
        'created_by', 'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'contact_id' => 'integer',
            'document_id' => 'integer',
            'transaction_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:4',
            'reversal_of_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<ContactTransaction, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
