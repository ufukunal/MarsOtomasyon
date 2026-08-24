<?php

namespace App\Modules\Core\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ExchangeRate extends Model
{
    protected $fillable = [
        'company_id',
        'rate_date',
        'from_currency_code',
        'to_currency_code',
        'rate',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rate_date' => 'immutable_date',
            'rate' => 'decimal:10',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function rateDateString(): string
    {
        $raw = $this->getRawOriginal('rate_date');

        if ($raw instanceof DateTimeInterface) {
            return $raw->format('Y-m-d');
        }

        if (! is_string($raw) || strlen($raw) < 10) {
            throw new LogicException('Persisted exchange rate date is invalid.');
        }

        return substr($raw, 0, 10);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
