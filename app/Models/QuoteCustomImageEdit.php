<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuoteCustomImageEdit extends PeriodModel
{
    public $timestamps = false;

    protected $table = 'quote_custom_image_edits';

    protected $fillable = [
        'attachment_id', 'disk', 'path', 'mime', 'size', 'instructions', 'created_by',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer', 'instructions' => 'array', 'created_at' => 'datetime'];
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }
}
