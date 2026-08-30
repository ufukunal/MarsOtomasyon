<?php

namespace App\Modules\Imports\Models;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ImportItem extends Model
{
    protected $fillable = ['company_id', 'import_file_id', 'import_container_id', 'product_id', 'package_reference', 'component_reference', 'quantity', 'package_count', 'gross_weight_kg', 'net_weight_kg', 'volume_m3', 'material_location', 'subcontract_collection', 'note'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'package_count' => 'integer', 'gross_weight_kg' => 'decimal:6', 'net_weight_kg' => 'decimal:6', 'volume_m3' => 'decimal:6', 'subcontract_collection' => 'boolean', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ImportContainer, $this> */
    public function container(): BelongsTo
    {
        return $this->belongsTo(ImportContainer::class, 'import_container_id');
    }

    /** @return HasMany<ImportReceiptLink, $this> */
    public function receiptLinks(): HasMany
    {
        return $this->hasMany(ImportReceiptLink::class);
    }
}
