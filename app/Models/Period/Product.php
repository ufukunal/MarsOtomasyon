<?php

namespace App\Models\Period;

use App\Contracts\HasAttachments as HasAttachmentsContract;
use App\Contracts\SearchIndexed;
use App\Enums\ChannelStockMode;
use App\Enums\ProductKind;
use App\Models\Concerns\HasAttachments;
use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use App\Support\Products\SetAvailabilityCalculator;
use App\Support\Search\HasSearchIndex;
use App\Support\Search\SearchNormalizer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @implements SearchIndexed<Product>
 * @property ProductKind $kind
 * @property ChannelStockMode $channel_stock_mode
 */
class Product extends PeriodModel implements HasAttachmentsContract, SearchIndexed
{
    use HasAttachments;
    use HasOptimisticLock;
    use HasSearchIndex;
    use LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category_id',
        'brand_id',
        'unit_id',
        'barcode',
        'vat_rate',
        'list_price',
        'currency',
        'kind',
        'variant_group_id',
        'allow_negative_stock',
        'min_stock',
        'channel_stock_mode',
        'source_company_id',
        'source_record_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:4',
            'list_price' => 'decimal:4',
            'kind' => ProductKind::class,
            'allow_negative_stock' => 'boolean',
            'min_stock' => 'decimal:3',
            'channel_stock_mode' => ChannelStockMode::class,
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            if ($product->exists && $product->isDirty('code')) {
                throw new LogicException('Ürün kodu kayıt sonrası değiştirilemez.');
            }

            $category = $product->category_id
                ? ProductCategory::query()->find($product->category_id)?->name
                : null;
            $brand = $product->brand_id
                ? Brand::query()->find($product->brand_id)?->name
                : null;

            $product->search_index = SearchNormalizer::make(implode(' ', array_filter([
                $product->code,
                $product->name,
                $product->barcode,
                $brand,
                $category,
            ])));
        });
    }

    public function searchableFields(): array
    {
        return ['code', 'name', 'barcode'];
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<VariantGroup, $this> */
    public function variantGroup(): BelongsTo
    {
        return $this->belongsTo(VariantGroup::class);
    }

    /** @return HasMany<ProductVariantValue, $this> */
    public function variantValues(): HasMany
    {
        return $this->hasMany(ProductVariantValue::class);
    }

    /** @return HasMany<ProductSet, $this> */
    public function setComponents(): HasMany
    {
        return $this->hasMany(ProductSet::class, 'set_product_id');
    }

    /** @return HasMany<ConfigDefinition, $this> */
    public function configDefinitions(): HasMany
    {
        return $this->hasMany(ConfigDefinition::class)->orderBy('sort_order');
    }

    /** @return HasMany<PriceListItem, $this> */
    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function availableQuantity(): string
    {
        if (! Schema::connection('period')->hasTable('stock_balances')) {
            return '0.000';
        }

        $row = DB::connection('period')
            ->table('stock_balances')
            ->where('product_id', $this->id)
            ->selectRaw(
                'COALESCE(SUM(quantity - reserved - consignment_reserved - quarantine), 0)::text AS available'
            )
            ->first();

        return bcadd($row ? (string) $row->available : '0', '0', 3);
    }

    public function setAvailability(): ?string
    {
        if ($this->kind !== ProductKind::Set) {
            return null;
        }

        return app(SetAvailabilityCalculator::class)->forProduct($this);
    }

    public function getBrandNameAttribute(): string
    {
        return (string) ($this->relationLoaded('brand') ? $this->brand?->name : $this->brand()->value('name'));
    }

    public function getCategoryNameAttribute(): string
    {
        return (string) ($this->relationLoaded('category') ? $this->category?->name : $this->category()->value('name'));
    }

    public function getUnitNameAttribute(): string
    {
        return (string) ($this->relationLoaded('unit') ? $this->unit?->name : $this->unit()->value('name'));
    }

    public function getAvailableQuantityAttribute(): string
    {
        return $this->availableQuantity();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('product')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
