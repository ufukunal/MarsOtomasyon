<?php

namespace App\Models;

use App\Models\Period\Document;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPrintSnapshot extends PeriodModel
{
    protected $fillable = [
        'document_id',
        'template_key',
        'template_revision_no',
        'rendered_at',
        'rendered_by',
        'output_hash',
    ];

    protected function casts(): array
    {
        return [
            'document_id' => 'integer',
            'template_revision_no' => 'integer',
            'rendered_at' => 'immutable_datetime',
            'rendered_by' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
