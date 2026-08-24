<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\PostingPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PostingPeriod extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'starts_on',
        'ends_on',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'status' => PostingPeriodStatus::class,
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
