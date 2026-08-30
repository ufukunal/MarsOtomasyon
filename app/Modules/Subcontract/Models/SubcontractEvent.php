<?php

namespace App\Modules\Subcontract\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SubcontractEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['company_id', 'subcontract_order_id', 'event_type', 'payload', 'occurred_at', 'created_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SubcontractOrder, $this> */
    public function order(): BelongsTo { return $this->belongsTo(SubcontractOrder::class, 'subcontract_order_id'); }
}
