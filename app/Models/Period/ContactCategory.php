<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactCategory extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['name', 'color', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Contact, $this> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            Contact::class,
            'contact_category',
            'contact_category_id',
            'contact_id',
        );
    }
}
