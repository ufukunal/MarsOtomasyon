<?php

namespace App\Modules\Imports\Models;

use App\Modules\Accounts\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ImportFile extends Model
{
    protected $fillable = ['company_id', 'supplier_account_id', 'number', 'status', 'currency_code', 'supplier_reference', 'origin_country', 'loading_port', 'destination_port', 'departure_date', 'expected_arrival_date', 'arrival_date', 'note', 'completed_at'];

    protected function casts(): array
    {
        return ['departure_date' => 'immutable_date', 'expected_arrival_date' => 'immutable_date', 'arrival_date' => 'immutable_date', 'completed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Account, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'supplier_account_id');
    }

    /** @return HasMany<ImportContainer, $this> */
    public function containers(): HasMany
    {
        return $this->hasMany(ImportContainer::class);
    }

    /** @return HasMany<ImportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }

    /** @return HasMany<ImportExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(ImportExpense::class);
    }
}
