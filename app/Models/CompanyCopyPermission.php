<?php

namespace App\Models;

use App\Enums\CompanyCopyPermissionType;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class CompanyCopyPermission extends MasterModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'source_company_id',
        'target_company_id',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyCopyPermissionType::class,
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $permission): void {
            if ((int) $permission->source_company_id === (int) $permission->target_company_id) {
                throw new InvalidArgumentException('Kaynak ve hedef şirket aynı olamaz.');
            }
        });
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Company, $this> */
    public function sourceCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'source_company_id');
    }

    public function targetCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'target_company_id');
    }

    public static function allows(
        int $sourceId,
        int $targetId,
        CompanyCopyPermissionType $type,
    ): bool {
        return static::query()
            ->where('source_company_id', $sourceId)
            ->where('target_company_id', $targetId)
            ->where('type', $type->value)
            ->where('is_active', true)
            ->exists();
    }
}
