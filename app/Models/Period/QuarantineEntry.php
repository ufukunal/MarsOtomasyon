<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarantineEntry extends PeriodModel
{
    protected $fillable = [
        'product_id',
        'location_id',
        'source_document_type',
        'source_document_id',
        'source_line_id',
        'quantity',
        'released_quantity',
        'scrapped_quantity',
        'reversed_quantity',
        'unit_cost',
        'status',
        'decision_note',
        'created_by',
        'created_by_name',
        'decided_by',
        'decided_by_name',
        'decided_at',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'released_quantity' => 'decimal:3',
            'scrapped_quantity' => 'decimal:3',
            'reversed_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'source_document_id' => 'integer',
            'source_line_id' => 'integer',
            'created_by' => 'integer',
            'decided_by' => 'integer',
            'decided_at' => 'datetime',
            'reversed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function pendingQuantity(): string
    {
        return bcsub(
            bcsub(
                bcsub((string) $this->quantity, (string) $this->released_quantity, 3),
                (string) $this->scrapped_quantity,
                3,
            ),
            (string) ($this->reversed_quantity ?? '0'),
            3,
        );
    }

    public function statusFor(string $released, string $scrapped): string
    {
        $decided = bcadd($released, $scrapped, 3);

        if (bccomp($decided, '0', 3) === 0) {
            return 'pending';
        }

        if (bccomp($released, (string) $this->quantity, 3) === 0) {
            return 'released';
        }

        if (bccomp($scrapped, (string) $this->quantity, 3) === 0) {
            return 'scrapped';
        }

        return 'partial';
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
