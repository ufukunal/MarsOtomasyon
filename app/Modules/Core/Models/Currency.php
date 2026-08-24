<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

final class Currency extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'minor_unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'minor_unit' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
