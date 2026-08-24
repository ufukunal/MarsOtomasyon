<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuditEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'company_id',
        'actor_user_id',
        'correlation_id',
        'source',
        'action',
        'target_type',
        'target_id',
        'before_state',
        'after_state',
        'metadata',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actionLabel(): string
    {
        $action = (string) $this->action;

        return AuditAction::tryFrom($action)?->label() ?? $action;
    }

    public function targetLabel(): string
    {
        $targetType = (string) $this->target_type;

        return AuditTargetType::tryFrom($targetType)?->label() ?? $targetType;
    }
}
