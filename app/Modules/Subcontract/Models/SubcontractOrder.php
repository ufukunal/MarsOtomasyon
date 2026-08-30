<?php

namespace App\Modules\Subcontract\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SubcontractOrder extends Model
{
    protected $fillable = [
        'company_id', 'supplier_account_id', 'output_product_id', 'warehouse_id', 'location_id',
        'order_no', 'status', 'planned_output_quantity', 'sent_value', 'loss_value',
        'received_output_quantity', 'received_output_value', 'sent_at', 'received_at', 'completed_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'planned_output_quantity' => 'decimal:6', 'sent_value' => 'decimal:6', 'loss_value' => 'decimal:6',
            'received_output_quantity' => 'decimal:6', 'received_output_value' => 'decimal:6',
            'sent_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function supplier(): BelongsTo { return $this->belongsTo(Account::class, 'supplier_account_id'); }

    /** @return BelongsTo<Product, $this> */
    public function outputProduct(): BelongsTo { return $this->belongsTo(Product::class, 'output_product_id'); }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function location(): BelongsTo { return $this->belongsTo(WarehouseLocation::class, 'location_id'); }

    /** @return HasMany<SubcontractOrderMaterial, $this> */
    public function materials(): HasMany { return $this->hasMany(SubcontractOrderMaterial::class, 'subcontract_order_id'); }

    /** @return HasMany<SubcontractReceipt, $this> */
    public function receipts(): HasMany { return $this->hasMany(SubcontractReceipt::class, 'subcontract_order_id'); }

    /** @return HasMany<SubcontractLoss, $this> */
    public function losses(): HasMany { return $this->hasMany(SubcontractLoss::class, 'subcontract_order_id'); }

    /** @return HasMany<SubcontractEvent, $this> */
    public function events(): HasMany { return $this->hasMany(SubcontractEvent::class, 'subcontract_order_id'); }
}
