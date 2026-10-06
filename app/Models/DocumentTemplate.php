<?php

namespace App\Models;

use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $company_id
 * @property string $template_key
 * @property string $name
 * @property int $revision_no
 * @property string $render_type
 * @property string|null $paper_code
 * @property string|null $width_mm
 * @property string|null $height_mm
 * @property array<string, mixed> $definition
 * @property bool $is_active
 * @property bool $is_default
 */
class DocumentTemplate extends MasterModel
{
    use HasOptimisticLock;
    use LogsActivity;

    private const IMMUTABLE_FIELDS = [
        'company_id',
        'template_key',
        'name',
        'revision_no',
        'render_type',
        'paper_code',
        'width_mm',
        'height_mm',
        'definition',
        'created_by',
    ];

    protected $fillable = [
        'company_id',
        'template_key',
        'name',
        'revision_no',
        'render_type',
        'paper_code',
        'width_mm',
        'height_mm',
        'definition',
        'is_active',
        'is_default',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'revision_no' => 'integer',
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'definition' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'version' => 'integer',
            'created_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $template): void {
            foreach (self::IMMUTABLE_FIELDS as $field) {
                if ($template->isDirty($field)) {
                    throw new LogicException(
                        "Final template revizyonu yerinde değiştirilemez: {$field}. Yeni revizyon oluşturun.",
                    );
                }
            }
        });
    }

    /** @return BelongsTo<Company,$this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User,$this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
