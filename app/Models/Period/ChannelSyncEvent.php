<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelSyncEvent extends PeriodModel
{
    protected $fillable = [
        'channel_account_id',
        'direction',
        'entity_type',
        'entity_id',
        'external_id',
        'action',
        'status',
        'attempts',
        'correlation_id',
        'payload_hash',
        'safe_metadata',
        'error_summary',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'channel_account_id' => 'integer',
            'entity_id' => 'integer',
            'attempts' => 'integer',
            'safe_metadata' => 'array',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ChannelSyncError::class);
    }
}
