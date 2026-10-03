<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactPerson extends PeriodModel
{
    use HasOptimisticLock;

    protected $table = 'contact_people';

    protected $fillable = ['contact_id', 'name', 'title', 'phone', 'email', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
