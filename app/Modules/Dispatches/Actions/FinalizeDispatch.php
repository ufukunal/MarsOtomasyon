<?php

namespace App\Modules\Dispatches\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Dispatches\Enums\DispatchStatus;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Models\DispatchLine;
use App\Modules\Dispatches\Stock\DispatchStockOutService;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Progress\SalesOrderProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class FinalizeDispatch
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private DispatchStockOutService $stockOut,
        private SalesOrderProgressService $progress,
        private Clock $clock,
    ) {}

    public function handle(int $dispatchId): Dispatch
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $dispatchId): Dispatch {
            $dispatch = Dispatch::query()
                ->where('company_id', $companyId)
                ->whereKey($dispatchId)
                ->lockForUpdate()
                ->first();

            if (! $dispatch instanceof Dispatch) {
                throw ValidationException::withMessages([
                    'dispatch' => 'İrsaliye aktif şirkette bulunamadı.',
                ]);
            }

            if ($dispatch->statusEnum() === DispatchStatus::Finalized) {
                return $dispatch;
            }

            if ($dispatch->statusEnum() !== DispatchStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Yalnız taslak irsaliye kesinleştirilebilir.',
                ]);
            }

            $lines = $dispatch->lines()->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'İrsaliye kesinleştirmek için en az bir satır içermelidir.',
                ]);
            }

            $this->stockOut->post($dispatch);

            /** @var DispatchLine $line */
            foreach ($lines as $line) {
                $this->progress->record(
                    new SourceEffectIdentity(
                        $companyId,
                        'dispatch_line',
                        (string) $line->getKey(),
                        'progress.dispatch',
                    ),
                    (int) $line->sales_order_line_id,
                    SalesOrderProgressType::Dispatched,
                    (string) $line->quantity,
                );
            }

            $dispatch->forceFill([
                'status' => DispatchStatus::Finalized,
                'finalized_at' => $this->clock->now(),
            ])->save();

            return $dispatch->refresh();
        });
    }
}
