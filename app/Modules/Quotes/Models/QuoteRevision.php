<?php

namespace App\Modules\Quotes\Models;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class QuoteRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'quote_id', 'revision_number', 'quote_number', 'account_id',
        'account_code', 'account_name', 'quote_date', 'valid_until', 'currency_code',
        'document_discount_rate', 'base_net_total', 'line_discount_total',
        'document_discount_total', 'net_total', 'tax_total', 'gross_total', 'note',
        'content_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'quote_date' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'document_discount_rate' => 'decimal:6',
            'base_net_total' => 'decimal:6',
            'line_discount_total' => 'decimal:6',
            'document_discount_total' => 'decimal:6',
            'net_total' => 'decimal:6',
            'tax_total' => 'decimal:6',
            'gross_total' => 'decimal:6',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return HasMany<QuoteRevisionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteRevisionLine::class, 'revision_id')->orderBy('position');
    }
}
