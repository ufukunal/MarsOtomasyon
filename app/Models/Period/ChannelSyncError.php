<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelSyncError extends PeriodModel
{
    protected $fillable = [
        'channel_sync_event_id',
        'error_summary',
        'resolved_at',
        'resolved_by',
        'resolved_by_name',
    ];

    protected function casts(): array
    {
        return [
            'channel_sync_event_id' => 'integer',
            'resolved_at' => 'datetime',
            'resolved_by' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ChannelSyncEvent::class, 'channel_sync_event_id');
    }
}
