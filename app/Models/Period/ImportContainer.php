<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

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
            if ($container->exists && $container->importFile()->value('status') && in_array(
                $container->importFile()->value('status'),
                ['received', 'closed'],
                true,
            )) {
                throw new LogicException('Teslim alınmış ithalat konteyneri değiştirilemez.');
            }
        };

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
