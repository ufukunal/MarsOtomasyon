<?php

namespace App\Modules\Dispatches\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Core\Models\Company;
use App\Modules\Dispatches\Enums\DispatchStatus;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class Dispatch extends Model
{
    protected $fillable = [
        'company_id', 'account_id', 'sales_order_id', 'source_address_id', 'number', 'series_code',
        'sequence_value', 'status', 'finalized_at', 'dispatch_date', 'recipient_name', 'address_line1', 'address_line2',
        'district', 'city', 'postal_code', 'country_code', 'carrier_name', 'carrier_service',
        'tracking_number', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => DispatchStatus::class,
            'sequence_value' => 'integer',
            'dispatch_date' => 'immutable_date',
            'finalized_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function statusEnum(): DispatchStatus
    {
        $raw = $this->getRawOriginal('status');
        if (! is_string($raw)) {
            throw new LogicException('Persisted dispatch status must be a string.');
        }

        return DispatchStatus::tryFrom($raw)
            ?? throw new LogicException('Persisted dispatch status is invalid.');
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

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<AccountAddress, $this> */
    public function sourceAddress(): BelongsTo
    {
        return $this->belongsTo(AccountAddress::class, 'source_address_id');
    }

    /** @return HasMany<DispatchLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DispatchLine::class)->orderBy('position');
    }
}
