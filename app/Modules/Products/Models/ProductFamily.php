<?php

namespace App\Modules\Products\Models;

use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductFamily extends Model
{
    protected $fillable = ['company_id', 'code', 'name', 'shared_content'];

    protected function casts(): array
    {
        return [
            'shared_content' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<VariantDimension, $this> */
    public function dimensions(): HasMany
    {
        return $this->hasMany(VariantDimension::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<ProductVariantRelation, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariantRelation::class);
    }

    /** @return HasMany<ProductFamilyChannelMapping, $this> */
    public function channelMappings(): HasMany
    {
        return $this->hasMany(ProductFamilyChannelMapping::class);
    }
}
