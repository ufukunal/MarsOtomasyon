<?php

namespace App\Modules\Accounts\Models;

use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\B2B\Enums\B2BRiskBehavior;
use App\Modules\Core\Models\Company;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccountB2BPolicy extends Model
{
    protected $table = 'account_b2b_policies';

    protected $fillable = [
        'company_id', 'account_id', 'is_enabled', 'allow_orders', 'show_price', 'show_stock', 'show_balance',
        'show_invoices', 'show_statement', 'allow_address_management', 'default_warehouse_id', 'risk_behavior',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'allow_orders' => 'boolean',
            'show_price' => 'boolean',
            'show_stock' => 'boolean',
            'show_balance' => 'boolean',
            'show_invoices' => 'boolean',
            'show_statement' => 'boolean',
            'allow_address_management' => 'boolean',
            'risk_behavior' => B2BRiskBehavior::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function allows(B2BPermission $permission): bool
    {
        if (! (bool) $this->is_enabled) {
            return false;
        }

        return match ($permission) {
            B2BPermission::PlaceOrders => (bool) $this->allow_orders,
            B2BPermission::ViewPrices => (bool) $this->show_price,
            B2BPermission::ViewStock => (bool) $this->show_stock,
            B2BPermission::ViewBalance => (bool) $this->show_balance,
            B2BPermission::ViewStatement => (bool) $this->show_statement,
            B2BPermission::ViewInvoices => (bool) $this->show_invoices,
            B2BPermission::ViewOrderHistory => true,
            B2BPermission::ManageAddresses => (bool) $this->allow_address_management,
        };
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }
}
