<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccountB2BPolicy extends Model
{
    protected $table = 'account_b2b_policies';

    protected $fillable = [
        'company_id',
        'account_id',
        'is_enabled',
        'allow_orders',
        'show_stock',
        'show_invoices',
        'show_statement',
        'allow_address_management',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'allow_orders' => 'boolean',
            'show_stock' => 'boolean',
            'show_invoices' => 'boolean',
            'show_statement' => 'boolean',
            'allow_address_management' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
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
}
