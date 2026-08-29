<?php

namespace App\Modules\SalesReturns\Actions;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\SalesReturns\Enums\SalesReturnStatus;
use App\Modules\SalesReturns\Models\SalesReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CancelSalesReturn
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
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
            if ($return->statusEnum() === SalesReturnStatus::Cancelled) {
                return $return->load('lines');
            }
            if (! in_array($return->statusEnum(), [SalesReturnStatus::Draft, SalesReturnStatus::Authorized], true)) {
                throw ValidationException::withMessages(['status' => 'Fiziksel kabulü yapılmış veya tamamlanmış RMA iptal edilemez.']);
            }

            $return->forceFill([
                'status' => SalesReturnStatus::Cancelled,
                'cancelled_at' => $this->clock->now(),
            ])->save();

            return $return->refresh()->load('lines');
        }, 3);
    }
}
