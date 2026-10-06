<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $import_file_id
 * @property string $container_no
 * @property string|null $container_type
 * @property string|null $seal_no
 * @property string|null $gross_weight_kg
 * @property string|null $volume_cbm
 * @property Carbon|null $etd
 * @property Carbon|null $eta
 * @property string $status
 */
class ImportContainer extends PeriodModel
{
    protected $table = 'containers';

    protected $fillable = [
        'import_file_id', 'container_no', 'container_type', 'seal_no',
        'gross_weight_kg', 'volume_cbm', 'etd', 'eta', 'status', 'notes',
    ];

    protected static function booted(): void
    {
        $guard = function (self $container): void {
            $fileIds = array_unique(array_filter([
                (int) ($container->import_file_id ?? 0),
                (int) ($container->getOriginal('import_file_id') ?? 0),
            ]));

            foreach ($fileIds as $fileId) {
                $status = ImportFile::query()->whereKey($fileId)->value('status');

                if (in_array((string) $status, ['received', 'closed'], true)) {
                    throw new LogicException('Teslim alınmış ithalat konteyneri değiştirilemez.');
                }
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    protected function casts(): array
    {
        return [
            'import_file_id' => 'integer',
            'gross_weight_kg' => 'decimal:3',
            'volume_cbm' => 'decimal:4',
            'etd' => 'date',
            'eta' => 'date',
        ];
    }

    /** @return BelongsTo<ImportFile, $this> */
    public function importFile(): BelongsTo
    {
        return $this->belongsTo(ImportFile::class);
    }

    /** @return HasMany<ImportPackage, $this> */
    public function packages(): HasMany
    {
        return $this->hasMany(ImportPackage::class, 'container_id');
    }
}
