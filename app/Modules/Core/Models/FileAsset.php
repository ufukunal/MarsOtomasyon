<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FileAsset extends Model
{
    protected $fillable = [
        'company_id',
        'uploaded_by_user_id',
        'storage_disk',
        'storage_key',
        'original_name',
        'mime_type',
        'client_extension',
        'size_bytes',
        'sha256',
        'archived_at',
        'archived_by_user_id',
        'quarantined_at',
        'quarantined_by_user_id',
        'quarantine_reason',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'archived_at' => 'immutable_datetime',
            'quarantined_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function quarantinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quarantined_by_user_id');
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function previewKind(): string
    {
        $mime = (string) $this->mime_type;

        if ($mime === 'application/pdf') {
            return 'pdf';
        }

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        return 'other';
    }
}
