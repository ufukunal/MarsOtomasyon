<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Attachment extends PeriodModel
{
    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'collection',
        'sort_order',
        'uploaded_by',
        'uploaded_by_name',
    ];

    /** @return HasMany<QuoteCustomImageEdit, $this> */
    public function editedVersions(): HasMany
    {
        return $this->hasMany(QuoteCustomImageEdit::class, 'attachment_id');
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
