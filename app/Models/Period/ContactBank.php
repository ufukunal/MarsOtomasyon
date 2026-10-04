<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactBank extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['contact_id', 'bank_name', 'iban', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
