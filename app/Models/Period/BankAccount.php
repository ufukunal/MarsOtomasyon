<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class BankAccount extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = ['code', 'bank_name', 'account_name', 'iban', 'currency', 'is_active'];

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

    public function bookBalance(): string
    {
        $value = $this->movements()
            ->where('origin', 'book')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0)::text AS balance")
            ->value('balance');

        return bcadd((string) ($value ?? '0'), '0', 4);
    }

    public function statementBalance(): ?string
    {
        $value = $this->movements()
            ->where('origin', 'statement')
            ->whereNotNull('statement_balance')
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->value('statement_balance');

        return $value === null ? null : bcadd((string) $value, '0', 4);
    }

    public function unreconciledStatementCount(): int
    {
        return $this->movements()
            ->where('origin', 'statement')
            ->whereNull('reconciled_movement_id')
            ->count();
    }
}
