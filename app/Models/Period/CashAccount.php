<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CashAccount extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['code', 'name', 'currency', 'is_active', 'version'];

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

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function balance(): string
    {
        $value = $this->movements()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0)::text AS balance")
            ->value('balance');

        return bcadd((string) ($value ?? '0'), '0', 4);
    }
}
