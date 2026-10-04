<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ImportPackage extends PeriodModel
{
    protected $table = 'packages';

    protected $fillable = [
        'import_file_id', 'container_id', 'carton_no', 'component_name',
        'product_id', 'location_id', 'quantity', 'unit_price', 'weight_kg',
        'volume_cbm', 'goods_value_try', 'allocated_cost_try',
        'landed_unit_cost_try', 'status', 'received_at', 'notes',
    ];

    protected static function booted(): void
    {
        $guard = function (self $package): void {
            $fileIds = array_unique(array_filter([
                (int) ($package->import_file_id ?? 0),
                (int) ($package->getOriginal('import_file_id') ?? 0),
            ]));

            foreach ($fileIds as $fileId) {
                $status = ImportFile::query()->whereKey($fileId)->value('status');

                if (in_array((string) $status, ['received', 'closed'], true)) {
                    throw new LogicException('Teslim alınmış ithalat paketi değiştirilemez.');
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
            'container_id' => 'integer',
            'product_id' => 'integer',
            'location_id' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'weight_kg' => 'decimal:4',
            'volume_cbm' => 'decimal:6',
            'goods_value_try' => 'decimal:4',
            'allocated_cost_try' => 'decimal:4',
            'landed_unit_cost_try' => 'decimal:4',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ImportFile, $this> */
    public function importFile(): BelongsTo
    {
        return $this->belongsTo(ImportFile::class);
    }

    /** @return BelongsTo<ImportContainer, $this> */
    public function container(): BelongsTo
    {
        return $this->belongsTo(ImportContainer::class, 'container_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<ImportCostAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ImportCostAllocation::class, 'package_id');
    }
}
