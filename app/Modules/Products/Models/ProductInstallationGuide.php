<?php

namespace App\Modules\Products\Models;

use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductInstallationGuide extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'title',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductInstallationGuideVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ProductInstallationGuideVersion::class, 'guide_id');
    }
}
