<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ImportCostAllocation extends PeriodModel
{
    protected $fillable = [
        'import_cost_item_id', 'package_id', 'product_id', 'container_id',
        'basis_value', 'allocation_ratio', 'allocated_amount_try',
    ];

    protected static function booted(): void
    {
        $guard = function (self $allocation): void {
            $costItemIds = array_unique(array_filter([
                (int) ($allocation->import_cost_item_id ?? 0),
                (int) ($allocation->getOriginal('import_cost_item_id') ?? 0),
            ]));

            foreach ($costItemIds as $costItemId) {
                $fileId = ImportCostItem::query()->whereKey($costItemId)->value('import_file_id');
                $status = $fileId
                    ? ImportFile::query()->whereKey((int) $fileId)->value('status')
                    : null;

                if (in_array((string) $status, ['received', 'closed'], true)) {
                    throw new LogicException('Teslim alınmış ithalat maliyet dağıtımı değiştirilemez.');
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
            'import_cost_item_id' => 'integer',
            'package_id' => 'integer',
            'product_id' => 'integer',
            'container_id' => 'integer',
            'basis_value' => 'decimal:8',
            'allocation_ratio' => 'decimal:10',
            'allocated_amount_try' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<ImportCostItem, $this> */
    public function costItem(): BelongsTo
    {
        return $this->belongsTo(ImportCostItem::class, 'import_cost_item_id');
    }

    /** @return BelongsTo<ImportPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ImportPackage::class, 'package_id');
    }
}
