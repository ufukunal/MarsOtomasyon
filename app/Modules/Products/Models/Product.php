<?php

namespace App\Modules\Products\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class Product extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'status',
        'name',
        'category_id',
        'unit_id',
        'tax_id',
        'sale_price_net',
        'purchase_price_net',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'sale_price_net' => 'decimal:6',
            'purchase_price_net' => 'decimal:6',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function statusEnum(): ProductStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted product status must be a string.');
        }

        return ProductStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted product status is invalid.');
    }

    public function isActive(): bool
    {
        return $this->statusEnum() === ProductStatus::Active;
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Tax, $this> */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /** @return HasMany<Barcode, $this> */
    public function barcodes(): HasMany
    {
        return $this->hasMany(Barcode::class);
    }
}
