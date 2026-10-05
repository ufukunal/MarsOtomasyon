<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ProductionRecipe extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'product_id', 'number', 'revision_no', 'output_quantity', 'is_active',
        'version', 'created_by', 'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'revision_no' => 'integer',
            'output_quantity' => 'decimal:3',
            'is_active' => 'boolean',
            'version' => 'integer',
            'created_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $recipe): void {
            $allowed = ['is_active', 'version', 'updated_at'];

            foreach (array_keys($recipe->getDirty()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    throw new LogicException('Reçete revizyonu immutable; yeni revizyon oluşturulmalıdır.');
                }
            }
        });

        static::deleting(fn (): never => throw new LogicException('Reçete revizyonu fiziksel olarak silinemez.'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionRecipeLine::class)->orderBy('id');
    }
}
