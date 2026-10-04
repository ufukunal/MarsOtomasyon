<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class DocumentLine extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'document_id', 'line_no', 'line_kind', 'product_id', 'description', 'unit_id',
        'quantity', 'conversion_factor', 'base_quantity', 'location_id', 'unit_price',
        'line_discount_rate', 'line_discount_amount', 'vat_rate', 'line_total',
        'reserve_stock', 'cancelled_quantity', 'configuration', 'source_line_id', 'version',
    ];

    protected static function booted(): void
    {
        $guard = function (DocumentLine $line): void {
            if ($line->exists && $line->document()->value('status') === 'posted') {
                throw new LogicException('Kesinleşmiş belge satırı yerinde değiştirilemez.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    protected function casts(): array
    {
        return [
            'document_id' => 'integer',
            'line_no' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:6',
            'base_quantity' => 'decimal:3',
            'location_id' => 'integer',
            'unit_price' => 'decimal:4',
            'line_discount_rate' => 'decimal:4',
            'line_discount_amount' => 'decimal:4',
            'vat_rate' => 'decimal:4',
            'line_total' => 'decimal:4',
            'reserve_stock' => 'boolean',
            'cancelled_quantity' => 'decimal:3',
            'configuration' => 'array',
            'source_line_id' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<DocumentLine, $this> */
    public function sourceLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_line_id');
    }

    /** @return HasMany<DocumentLine, $this> */
    public function childLines(): HasMany
    {
        return $this->hasMany(self::class, 'source_line_id');
    }
}
