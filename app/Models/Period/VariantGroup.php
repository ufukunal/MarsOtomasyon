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

    public function attributes(): HasMany
    {
        return $this->hasMany(VariantAttribute::class)->orderBy('sort_order')->orderBy('id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
