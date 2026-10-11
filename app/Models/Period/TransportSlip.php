<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportSlip extends PeriodModel
{
    protected $fillable = [
        'number', 'slip_date', 'contact_id', 'location_id',
        'order_reference', 'dispatch_reference', 'shipping_type', 'carrier',
        'destination_warehouse', 'package_count', 'weight_kg', 'tracking_number',
        'contains_glass', 'fragile', 'keep_upright', 'do_not_stack',
        'delivery_company', 'delivery_person', 'delivery_phone',
        'delivery_city', 'delivery_district', 'delivery_address',
        'note', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'slip_date' => 'date',
            'package_count' => 'integer',
            'weight_kg' => 'decimal:2',
            'contains_glass' => 'boolean',
            'fragile' => 'boolean',
            'keep_upright' => 'boolean',
            'do_not_stack' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<TransportSlipItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TransportSlipItem::class);
    }
}
