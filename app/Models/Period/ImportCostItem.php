<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ImportCostItem extends PeriodModel
{
    protected $fillable = [
        'import_file_id', 'name', 'amount', 'currency', 'exchange_rate',
        'amount_try', 'allocation_basis', 'allocated_at', 'notes',
    ];

    protected static function booted(): void
    {
        $guard = function (self $item): void {
            $fileIds = array_unique(array_filter([
                (int) ($item->import_file_id ?? 0),
                (int) ($item->getOriginal('import_file_id') ?? 0),
            ]));

            foreach ($fileIds as $fileId) {
                $status = ImportFile::query()->whereKey($fileId)->value('status');

                if (in_array((string) $status, ['received', 'closed'], true)) {
                    throw new LogicException('Teslim alınmış ithalat maliyet kalemi değiştirilemez.');
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
            'amount' => 'decimal:4',
            'exchange_rate' => 'decimal:6',
            'amount_try' => 'decimal:4',
            'allocated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ImportFile, $this> */
    public function importFile(): BelongsTo
    {
        return $this->belongsTo(ImportFile::class);
    }

    /** @return HasMany<ImportCostAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ImportCostAllocation::class);
    }
}
