<?php

namespace App\Modules\SalesReturns\Actions;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\SalesInvoices\Models\SalesInvoiceLine;
use App\Modules\SalesReturns\Enums\SalesReturnStatus;
use App\Modules\SalesReturns\Models\SalesReturn;
use App\Modules\SalesReturns\Models\SalesReturnLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReceiveSalesReturn
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private Clock $clock,
    ) {}

    /** @param list<SalesReturnInspectionLineData> $inspection */
    public function handle(int $salesReturnId, array $inspection): SalesReturn
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $salesReturnId, $inspection): SalesReturn {
            $return = SalesReturn::query()
                ->where('company_id', $companyId)
                ->whereKey($salesReturnId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($return->statusEnum() === SalesReturnStatus::Received) {
                return $return->load('lines');
            }
            if ($return->statusEnum() !== SalesReturnStatus::Authorized) {
                throw ValidationException::withMessages(['status' => 'Fiziksel kabul kontrolü yalnız yetkilendirilmiş RMA için yapılabilir.']);
            }

            $lines = $return->lines()->lockForUpdate()->get();
            $inspectionByLine = [];
            foreach ($inspection as $row) {
                if (isset($inspectionByLine[$row->salesReturnLineId])) {
                    throw ValidationException::withMessages(['lines' => 'Aynı RMA satırı için birden fazla kontrol kaydı gönderilemez.']);
                }
                $inspectionByLine[$row->salesReturnLineId] = $row;
            }
            if (count($inspectionByLine) !== $lines->count()) {
                throw ValidationException::withMessages(['lines' => 'Tüm RMA satırları için kabul/red kontrolü tamamlanmalıdır.']);
            }

            $creditedNetTotal = '0.000000';
            $creditedTaxTotal = '0.000000';
            $creditedGrossTotal = '0.000000';
            foreach ($lines as $line) {
                $row = $inspectionByLine[(int) $line->getKey()] ?? null;
                if (! $row instanceof SalesReturnInspectionLineData) {
                    throw ValidationException::withMessages(['lines' => 'RMA kontrol satırı aktif iadeye ait değildir.']);
                }
                $accepted = $this->nonNegativeDecimal($row->acceptedQuantity, 'accepted_quantity');
                $rejected = $this->nonNegativeDecimal($row->rejectedQuantity, 'rejected_quantity');
                $restock = $this->nonNegativeDecimal($row->restockQuantity, 'restock_quantity');
                $inspected = $this->add($accepted, $rejected);
                if (! $this->equal($inspected, (string) $line->quantity)) {
                    throw ValidationException::withMessages(['lines' => sprintf('%s için kabul + red miktarı iade miktarına eşit olmalıdır.', $line->product_code)]);
                }
                if ($this->greaterThan($restock, $accepted)) {
                    throw ValidationException::withMessages(['lines' => sprintf('%s için stoğa dönüş miktarı kabul edilen miktarı aşamaz.', $line->product_code)]);
                }

                $source = SalesInvoiceLine::query()
                    ->where('company_id', $companyId)
                    ->where('sales_invoice_id', $return->sales_invoice_id)
                    ->whereKey($line->sales_invoice_line_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $creditedNet = $this->proportion((string) $source->net_total, $accepted, (string) $source->quantity);
                $creditedTax = $this->proportion((string) $source->tax_total, $accepted, (string) $source->quantity);
                $creditedGross = $this->add($creditedNet, $creditedTax);
                $unitCost = $this->sourceUnitCost($companyId, $source, $restock);

                $line->forceFill([
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => $rejected,
                    'restock_quantity' => $restock,
                    'condition_notes' => $row->conditionNotes === null ? null : trim($row->conditionNotes),
                    'credited_net' => $creditedNet,
                    'credited_tax' => $creditedTax,
                    'credited_gross' => $creditedGross,
                    'unit_cost' => $unitCost,
                ])->save();

                $creditedNetTotal = $this->add($creditedNetTotal, $creditedNet);
                $creditedTaxTotal = $this->add($creditedTaxTotal, $creditedTax);
                $creditedGrossTotal = $this->add($creditedGrossTotal, $creditedGross);
            }

            $return->forceFill([
                'credited_net_total' => $creditedNetTotal,
                'credited_tax_total' => $creditedTaxTotal,
                'credited_gross_total' => $creditedGrossTotal,
                'status' => SalesReturnStatus::Received,
                'received_at' => $this->clock->now(),
            ])->save();

            return $return->refresh()->load('lines');
        }, 3);
    }

    private function sourceUnitCost(int $companyId, SalesInvoiceLine $source, string $restock): ?string
    {
        if (! $this->greaterThan($restock, '0')) {
            return null;
        }

        $dispatchLineId = $source->source_dispatch_line_id;
        $sourceType = $dispatchLineId === null ? 'sales_invoice_line' : 'dispatch_line';
        $sourceId = $dispatchLineId === null ? (string) $source->getKey() : (string) $dispatchLineId;
        $movementType = $dispatchLineId === null ? 'invoice_out' : 'dispatch_out';
        $movement = DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('effect_type', 'stock.out')
            ->where('movement_type', $movementType)
            ->first();
        if ($movement === null || ! $this->greaterThan((string) $movement->unit_cost, '0')) {
            throw ValidationException::withMessages([
                'lines' => sprintf('%s için orijinal stok çıkış maliyeti bulunamadı; stoğa dönüş yapılamaz.', $source->product_code),
            ]);
        }

        return (string) $movement->unit_cost;
    }

    private function nonNegativeDecimal(string $value, string $field): string
    {
        $value = trim($value);
        if (preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages([$field => 'Miktar en fazla 6 ondalıklı negatif olmayan sayı olmalıdır.']);
        }

        return (string) DB::scalar('SELECT CAST(CAST(? AS numeric(20,6)) AS text)', [$value]);
    }

    private function proportion(string $amount, string $quantity, string $sourceQuantity): string
    {
        if (! $this->greaterThan($quantity, '0')) {
            return '0.000000';
        }

        return (string) DB::scalar(
            'SELECT CAST(round(CAST(? AS numeric) * CAST(? AS numeric) / CAST(? AS numeric), 6) AS text)',
            [$amount, $quantity, $sourceQuantity],
        );
    }

    private function add(string $left, string $right): string
    {
        return (string) DB::scalar('SELECT CAST(CAST(? AS numeric(20,6)) + CAST(? AS numeric(20,6)) AS text)', [$left, $right]);
    }

    private function equal(string $left, string $right): bool
    {
        return (bool) DB::scalar('SELECT CAST(? AS numeric) = CAST(? AS numeric)', [$left, $right]);
    }

    private function greaterThan(string $left, string $right): bool
    {
        return (bool) DB::scalar('SELECT CAST(? AS numeric) > CAST(? AS numeric)', [$left, $right]);
    }
}
