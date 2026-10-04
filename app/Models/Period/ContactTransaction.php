<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
