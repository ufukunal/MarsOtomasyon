<?php

namespace App\Modules\SalesReturns\Actions;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\SalesInvoices\Models\SalesInvoiceLine;
use App\Modules\SalesReturns\Enums\SalesReturnStatus;
use App\Modules\SalesReturns\Models\SalesReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AuthorizeSalesReturn
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
            if ($return->statusEnum() === SalesReturnStatus::Authorized) {
                return $return->load('lines');
            }
            if ($return->statusEnum() !== SalesReturnStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Yalnız taslak RMA yetkilendirilebilir.']);
            }

            $lines = $return->lines()->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'RMA en az bir satır içermelidir.']);
            }

            foreach ($lines as $line) {
                $source = SalesInvoiceLine::query()
                    ->where('company_id', $companyId)
                    ->whereKey($line->sales_invoice_line_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $reserved = (string) DB::table('sales_return_lines as line')
                    ->join('sales_returns as header', function ($join): void {
                        $join->on('header.company_id', '=', 'line.company_id')
                            ->on('header.id', '=', 'line.sales_return_id');
                    })
                    ->where('line.company_id', $companyId)
                    ->where('line.sales_invoice_line_id', $line->sales_invoice_line_id)
                    ->where('header.id', '<>', $return->getKey())
                    ->whereIn('header.status', ['authorized', 'received', 'completed'])
                    ->selectRaw('COALESCE(SUM(line.quantity), 0) AS quantity')
                    ->value('quantity');
                $remaining = (string) DB::scalar('SELECT CAST(CAST(? AS numeric) - CAST(? AS numeric) AS text)', [(string) $source->quantity, $reserved]);
                if ((bool) DB::scalar('SELECT CAST(? AS numeric) > CAST(? AS numeric)', [(string) $line->quantity, $remaining])) {
                    throw ValidationException::withMessages([
                        'lines' => sprintf('%s için kalan iade kapasitesi %s; istenen %s.', $line->product_code, $remaining, $line->quantity),
                    ]);
                }
            }

            $return->forceFill([
                'status' => SalesReturnStatus::Authorized,
                'authorized_at' => $this->clock->now(),
            ])->save();

            return $return->refresh()->load('lines');
        }, 3);
    }
}
