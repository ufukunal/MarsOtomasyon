<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelExternalEventRegistry extends MasterModel
{
    protected $table = 'channel_external_event_registry';

    protected $fillable = [
        'channel_account_id',
        'event_type',
        'external_id',
        'period_id',
        'period_document_id',
        'external_occurred_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'channel_account_id' => 'integer',
            'period_id' => 'integer',
            'period_document_id' => 'integer',
            'external_occurred_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SalesChannelAccount::class, 'channel_account_id');
    }
}
