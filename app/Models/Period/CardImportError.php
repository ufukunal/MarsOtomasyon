<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardImportError extends PeriodModel
{
    protected $fillable = ['batch_id', 'row_no', 'column_name', 'value', 'message'];

    /** @return BelongsTo<CardImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CardImportBatch::class, 'batch_id');
    }
}
