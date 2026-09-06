<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CadViewerPolicy extends Model
{
    protected $fillable = [
        'company_id',
        'provider',
        'cloud_upload_enabled',
        'max_file_size_bytes',
        'timeout_seconds',
        'retention_days',
    ];

    protected function casts(): array
    {
        return [
            'cloud_upload_enabled' => 'boolean',
            'max_file_size_bytes' => 'integer',
            'timeout_seconds' => 'integer',
            'retention_days' => 'integer',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
