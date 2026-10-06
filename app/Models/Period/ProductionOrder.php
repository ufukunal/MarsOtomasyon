<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $document_date
 * @property int $product_id
 * @property int $recipe_id
 * @property string $planned_quantity
 * @property string $completed_quantity
 * @property string $cancelled_quantity
 * @property string $production_type
 * @property string $status
 */
class ProductionOrder extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'number', 'document_date', 'product_id', 'recipe_id', 'recipe_revision_no',
        'planned_quantity', 'completed_quantity', 'cancelled_quantity', 'production_type',
        'subcontractor_contact_id', 'subcontractor_location_id', 'source_sales_order_id',
        'status', 'notes', 'version', 'created_by', 'created_by_name',
        'confirmed_by', 'confirmed_by_name',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'product_id' => 'integer',
            'recipe_id' => 'integer',
            'recipe_revision_no' => 'integer',
            'planned_quantity' => 'decimal:3',
            'completed_quantity' => 'decimal:3',
            'cancelled_quantity' => 'decimal:3',
            'subcontractor_contact_id' => 'integer',
            'subcontractor_location_id' => 'integer',
            'source_sales_order_id' => 'integer',
            'version' => 'integer',
            'created_by' => 'integer',
            'confirmed_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $order): void {
            if ($order->getOriginal('status') === 'draft') {
                return;
            }

            $allowed = [
                'status', 'completed_quantity', 'cancelled_quantity', 'version',
                'confirmed_by', 'confirmed_by_name', 'number', 'updated_at',
            ];

            foreach (array_keys($order->getDirty()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    throw new LogicException('Onaylanmış üretim emrinin plan/snapshot alanları değiştirilemez.');
                }
            }
        });

        static::deleting(function (self $order): void {
            if ($order->status !== 'draft') {
                throw new LogicException('Yalnız taslak üretim emri silinebilir.');
            }
        });
    }

    public function remainingQuantity(): string
    {
        return bcsub(
            bcsub((string) $this->planned_quantity, (string) $this->completed_quantity, 3),
            (string) $this->cancelled_quantity,
            3,
        );
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductionRecipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'recipe_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'subcontractor_contact_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function subcontractorLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'subcontractor_location_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function sourceSalesOrder(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_sales_order_id');
    }

    /** @return HasMany<ProductionOrderComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(ProductionOrderComponent::class)->orderBy('id');
    }

    /** @return HasMany<ProductionCompletion, $this> */
    public function completions(): HasMany
    {
        return $this->hasMany(ProductionCompletion::class)->orderBy('id');
    }

    /** @return HasMany<ProductionServiceInvoice, $this> */
    public function serviceInvoices(): HasMany
    {
        return $this->hasMany(ProductionServiceInvoice::class);
    }
}
