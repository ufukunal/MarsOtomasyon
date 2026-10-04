<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRelation extends PeriodModel
{
    protected $fillable = [
        'source_document_id', 'target_document_id', 'relation_type', 'created_by', 'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'source_document_id' => 'integer',
            'target_document_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function targetDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }
}
