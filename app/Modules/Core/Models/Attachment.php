<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\AttachmentTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Attachment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'file_asset_id',
        'attachable_type',
        'attachable_id',
        'label',
        'attached_by_user_id',
        'attached_at',
        'detached_at',
        'detached_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'attachable_type' => AttachmentTargetType::class,
            'attached_at' => 'immutable_datetime',
            'detached_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<FileAsset, $this> */
    public function fileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function attachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function detachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detached_by_user_id');
    }

    public function isDetached(): bool
    {
        return $this->detached_at !== null;
    }
}
