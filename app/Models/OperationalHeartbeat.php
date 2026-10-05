<?php

namespace App\Models;

class OperationalHeartbeat extends MasterModel
{
    protected $primaryKey = 'service_key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['service_key','last_seen_at','metadata'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
