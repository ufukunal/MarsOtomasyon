<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentSequence extends Model
{
    protected $fillable = [
        'company_id',
        'document_type',
        'series_code',
        'prefix',
        'padding',
        'next_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'document_type' => DocumentType::class,
            'padding' => 'integer',
            'next_value' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function exampleNumber(): string
    {
        return $this->prefix.str_pad((string) $this->next_value, $this->padding, '0', STR_PAD_LEFT);
    }
}
