<?php

namespace App\Modules\SalesReturns\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\SalesReturns\Enums\SalesReturnStatus;
use App\Modules\SalesReturns\Models\SalesReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CompleteSalesReturn
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AccountTransactionPoster $accountTransactions,
        private StockMovementPoster $stockMovements,
        private Clock $clock,
    ) {}

    public function handle(int $salesReturnId): SalesReturn
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $salesReturnId): SalesReturn {
            $return = SalesReturn::query()
                ->where('company_id', $companyId)
                ->whereKey($salesReturnId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($return->statusEnum() === SalesReturnStatus::Completed) {
                return $return->load('lines');
            }
            if ($return->statusEnum() !== SalesReturnStatus::Received) {
                throw ValidationException::withMessages(['status' => 'RMA yalnız fiziksel kabul kontrolünden sonra tamamlanabilir.']);
            }

            $lines = $return->lines()->lockForUpdate()->get();
            if ($this->greaterThan((string) $return->credited_gross_total, '0')) {
                $this->accountTransactions->post(new PostAccountTransactionData(
                    accountId: (int) $return->account_id,
                    postingDate: (string) $return->getRawOriginal('return_date'),
                    signedAmount: $this->negate((string) $return->credited_gross_total),
                    sourceEffect: new SourceEffectIdentity(
                        $companyId,
                        'sales_return',
                        (string) $return->getKey(),
                        'account.sales_return',
                    ),
                    memo: 'Satış iadesi / RMA '.$return->number,
                ));
            }

            foreach ($lines as $line) {
                if (! $this->greaterThan((string) $line->restock_quantity, '0')) {
                    continue;
                }
                if ($line->unit_cost === null) {
                    throw ValidationException::withMessages(['lines' => 'Stoğa dönecek RMA satırı orijinal maliyet snapshotı içermiyor.']);
                }
                $this->stockMovements->post(new PostStockMovementData(
                    sourceEffect: new SourceEffectIdentity(
                        $companyId,
                        'sales_return_line',
                        (string) $line->getKey(),
                        'stock.in',
                    ),
                    productId: (int) $line->product_id,
                    warehouseId: (int) $line->warehouse_id,
                    locationId: (int) $line->location_id,
                    movementType: StockMovementType::SalesReturnIn,
                    quantity: (string) $line->restock_quantity,
                    unitCost: (string) $line->unit_cost,
                    note: 'Satış iadesi / RMA '.$return->number,
                ));
            }

            $return->forceFill([
                'status' => SalesReturnStatus::Completed,
                'completed_at' => $this->clock->now(),
            ])->save();

            return $return->refresh()->load('lines');
        }, 3);
    }

    private function negate(string $value): string
    {
        return (string) DB::scalar('SELECT CAST(-CAST(? AS numeric(20,6)) AS text)', [$value]);
    }

    private function greaterThan(string $left, string $right): bool
    {
        return (bool) DB::scalar('SELECT CAST(? AS numeric) > CAST(? AS numeric)', [$left, $right]);
    }
}
