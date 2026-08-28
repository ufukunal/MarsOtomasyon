<?php

namespace App\Modules\SalesReturns\Actions;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesInvoices\Models\SalesInvoiceLine;
use App\Modules\SalesReturns\Enums\SalesReturnStatus;
use App\Modules\SalesReturns\Models\SalesReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateSalesReturn
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private DocumentNumberIssuer $numbers,
    ) {}

    public function handle(SalesReturnDraftData $data, string $seriesCode = 'default'): SalesReturn
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $seriesCode = mb_strtolower(trim($seriesCode));
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $seriesCode) !== 1 || strlen($seriesCode) > 64) {
            throw ValidationException::withMessages(['series_code' => 'Satış iadesi numara serisi canonical ve en fazla 64 karakter olmalıdır.']);
        }

        return DB::transaction(function () use ($companyId, $data, $seriesCode): SalesReturn {
            $invoice = SalesInvoice::query()
                ->where('company_id', $companyId)
                ->whereKey($data->salesInvoiceId)
                ->lockForUpdate()
                ->first();
            if (! $invoice instanceof SalesInvoice || $invoice->statusEnum() !== SalesInvoiceStatus::Finalized) {
                throw ValidationException::withMessages(['sales_invoice_id' => 'RMA yalnız kesinleşmiş satış faturası üzerinden açılabilir.']);
            }
            if ($data->returnDate < $invoice->invoice_date->format('Y-m-d')) {
                throw ValidationException::withMessages(['return_date' => 'İade tarihi satış faturası tarihinden önce olamaz.']);
            }

            $sourceLines = SalesInvoiceLine::query()
                ->where('company_id', $companyId)
                ->where('sales_invoice_id', $invoice->getKey())
                ->whereIn('id', array_map(static fn (SalesReturnLineData $line): int => $line->salesInvoiceLineId, $data->lines))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $resolved = [];
            $seen = [];
            $requestedNet = '0.000000';
            $requestedTax = '0.000000';
            $requestedGross = '0.000000';
            foreach (array_values($data->lines) as $index => $line) {
                if (isset($seen[$line->salesInvoiceLineId])) {
                    throw ValidationException::withMessages(['lines' => 'Aynı fatura satırı bir RMA içinde yalnız bir kez kullanılabilir.']);
                }
                $seen[$line->salesInvoiceLineId] = true;

                $source = $sourceLines->get($line->salesInvoiceLineId);
                if (! $source instanceof SalesInvoiceLine) {
                    throw ValidationException::withMessages(['lines' => 'İade satırı aktif şirkette seçilen satış faturasına ait değildir.']);
                }
                $quantity = $this->positiveDecimal($line->quantity, 'lines.'.($index + 1).'.quantity');
                if ($this->greaterThan($quantity, (string) $source->quantity)) {
                    throw ValidationException::withMessages(['lines' => 'İade miktarı kaynak fatura satırı miktarını aşamaz.']);
                }

                $net = $this->proportion((string) $source->net_total, $quantity, (string) $source->quantity);
                $tax = $this->proportion((string) $source->tax_total, $quantity, (string) $source->quantity);
                $gross = $this->add($net, $tax);
                $requestedNet = $this->add($requestedNet, $net);
                $requestedTax = $this->add($requestedTax, $tax);
                $requestedGross = $this->add($requestedGross, $gross);

                $resolved[] = [
                    'source' => $source,
                    'quantity' => $quantity,
                    'reason_code' => mb_strtolower(trim($line->reasonCode)),
                    'net' => $net,
                    'tax' => $tax,
                    'gross' => $gross,
                ];
            }

            $number = $this->numbers->issue($companyId, DocumentType::SalesReturn, $seriesCode);
            $return = SalesReturn::query()->create([
                'company_id' => $companyId,
                'sales_invoice_id' => $invoice->getKey(),
                'account_id' => $invoice->account_id,
                'number' => $number->number,
                'series_code' => $number->seriesCode,
                'sequence_value' => $number->sequenceValue,
                'status' => SalesReturnStatus::Draft,
                'return_date' => $data->returnDate,
                'currency_code' => $invoice->currency_code,
                'requested_net_total' => $requestedNet,
                'requested_tax_total' => $requestedTax,
                'requested_gross_total' => $requestedGross,
                'credited_net_total' => '0.000000',
                'credited_tax_total' => '0.000000',
                'credited_gross_total' => '0.000000',
                'note' => $data->note === null ? null : trim($data->note),
            ]);

            foreach ($resolved as $index => $line) {
                /** @var SalesInvoiceLine $source */
                $source = $line['source'];
                $return->lines()->create([
                    'company_id' => $companyId,
                    'sales_invoice_id' => $invoice->getKey(),
                    'sales_invoice_line_id' => $source->getKey(),
                    'position' => $index + 1,
                    'product_id' => $source->product_id,
                    'warehouse_id' => $source->warehouse_id,
                    'location_id' => $source->location_id,
                    'product_code' => $source->product_code,
                    'product_name' => $source->product_name,
                    'reason_code' => $line['reason_code'],
                    'condition_notes' => null,
                    'quantity' => $line['quantity'],
                    'accepted_quantity' => '0.000000',
                    'rejected_quantity' => '0.000000',
                    'restock_quantity' => '0.000000',
                    'requested_net' => $line['net'],
                    'requested_tax' => $line['tax'],
                    'requested_gross' => $line['gross'],
                    'credited_net' => '0.000000',
                    'credited_tax' => '0.000000',
                    'credited_gross' => '0.000000',
                    'unit_cost' => null,
                ]);
            }

            return $return->load('lines');
        }, 3);
    }

    private function positiveDecimal(string $value, string $field): string
    {
        $value = trim($value);
        if (preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages([$field => 'Miktar en fazla 6 ondalıklı pozitif sayı olmalıdır.']);
        }
        $normalized = (string) DB::scalar('SELECT CAST(CAST(? AS numeric(20,6)) AS text)', [$value]);
        if (! $this->greaterThan($normalized, '0')) {
            throw ValidationException::withMessages([$field => 'Miktar sıfırdan büyük olmalıdır.']);
        }

        return $normalized;
    }

    private function proportion(string $amount, string $quantity, string $sourceQuantity): string
    {
        return (string) DB::scalar(
            'SELECT CAST(round(CAST(? AS numeric) * CAST(? AS numeric) / CAST(? AS numeric), 6) AS text)',
            [$amount, $quantity, $sourceQuantity],
        );
    }

    private function add(string $left, string $right): string
    {
        return (string) DB::scalar('SELECT CAST(CAST(? AS numeric(20,6)) + CAST(? AS numeric(20,6)) AS text)', [$left, $right]);
    }

    private function greaterThan(string $left, string $right): bool
    {
        return (bool) DB::scalar('SELECT CAST(? AS numeric) > CAST(? AS numeric)', [$left, $right]);
    }
}
