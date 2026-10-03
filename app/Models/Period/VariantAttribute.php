<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantAttribute extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['variant_group_id', 'name', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'version' => 'integer'];
    }

    /** @return BelongsTo<VariantGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(VariantGroup::class, 'variant_group_id');
    }

    /** @return HasMany<ProductVariantValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(ProductVariantValue::class);
    }
}
