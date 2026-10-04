<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class BankAccount extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['code', 'bank_name', 'account_name', 'iban', 'currency', 'is_active', 'version'];

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Finans hesabı fiziksel olarak silinemez; pasife alınmalıdır.');
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'version' => 'integer'];
    }

    /** @return HasMany<BankMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(BankMovement::class);
    }
}
