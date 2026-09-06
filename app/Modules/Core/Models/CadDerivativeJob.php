<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CadDerivativeJob extends Model
{
    protected $fillable = [
        'company_id',
        'attachment_id',
        'file_asset_id',
        'source_sha256',
        'source_extension',
        'provider',
        'provider_version',
        'status',
        'preview_kind',
        'provider_job_id',
        'manifest',
        'derivative_sha256',
        'failure_code',
        'failure_message',
        'generated_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'generated_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Attachment, $this> */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    /** @return BelongsTo<FileAsset, $this> */
    public function fileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }
}
