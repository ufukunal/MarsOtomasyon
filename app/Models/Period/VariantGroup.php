<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantGroup extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'version' => 'integer'];
    }

    /** @return HasMany<VariantAttribute, $this> */
    public function attributes(): HasMany
    {
        return $this->hasMany(VariantAttribute::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
