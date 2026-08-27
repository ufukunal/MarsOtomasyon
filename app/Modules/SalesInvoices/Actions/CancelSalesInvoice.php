<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Ledger\AccountTransactionReverser;
use App\Modules\Accounts\Models\AccountTransaction;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesInvoices\Models\SalesInvoiceLine;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Progress\SalesOrderProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class CancelSalesInvoice
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AccountTransactionReverser $accountTransactions,
        private SalesOrderProgressService $progress,
        private Clock $clock,
    ) {}

    public function handle(int $salesInvoiceId): SalesInvoice
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $salesInvoiceId): SalesInvoice {
            $invoice = SalesInvoice::query()
                ->where('company_id', $companyId)
                ->whereKey($salesInvoiceId)
                ->lockForUpdate()
                ->first();

            if (! $invoice instanceof SalesInvoice) {
                throw ValidationException::withMessages([
                    'sales_invoice' => 'Satış faturası aktif şirkette bulunamadı.',
                ]);
            }

            if ($invoice->statusEnum() === SalesInvoiceStatus::Cancelled) {
                return $invoice;
            }

            if ($invoice->statusEnum() !== SalesInvoiceStatus::Finalized) {
                throw ValidationException::withMessages([
                    'status' => 'Yalnız kesinleşmiş satış faturası iptal edilebilir.',
                ]);
            }

            $lines = $invoice->lines()->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw new LogicException('Kesinleşmiş satış faturası satırsız olamaz.');
            }

            $original = AccountTransaction::query()
                ->where('company_id', $companyId)
                ->where('account_id', $invoice->account_id)
                ->where('source_type', 'sales_invoice')
                ->where('source_id', (string) $invoice->getKey())
                ->where('effect_type', 'account.sales_invoice')
                ->first();

            if (! $original instanceof AccountTransaction) {
                throw new LogicException('Satış faturası cari effecti bulunamadı.');
            }

            $cancelledAt = $this->clock->now();

            $this->accountTransactions->reverse(
                originalTransactionId: (int) $original->getKey(),
                postingDate: $cancelledAt->format('Y-m-d'),
                sourceEffect: $this->identity($invoice, 'account.sales_invoice.reverse'),
                memo: 'Satış faturası iptali '.$invoice->number,
            );

            /** @var SalesInvoiceLine $line */
            foreach ($lines as $line) {
                if ($line->source_sales_order_line_id === null) {
                    continue;
                }

                $progressEffect = SalesOrderLineProgressEffect::query()
                    ->where('company_id', $companyId)
                    ->where('sales_order_line_id', $line->source_sales_order_line_id)
                    ->where('source_type', 'sales_invoice_line')
                    ->where('source_id', (string) $line->getKey())
                    ->where('effect_type', 'progress.invoice')
                    ->where('progress_type', SalesOrderProgressType::Invoiced->value)
                    ->first();

                if (! $progressEffect instanceof SalesOrderLineProgressEffect) {
                    throw new LogicException('Satış faturası sipariş progress effecti bulunamadı.');
                }

                $this->progress->reverse(
                    $this->lineIdentity($line, 'progress.invoice.reverse'),
                    (int) $progressEffect->getKey(),
                );
            }

            $invoice->forceFill([
                'status' => SalesInvoiceStatus::Cancelled,
                'cancelled_at' => $cancelledAt,
            ])->save();

            return $invoice->refresh();
        });
    }

    private function identity(SalesInvoice $invoice, string $effectType): SourceEffectIdentity
    {
        return new SourceEffectIdentity(
            (int) $invoice->company_id,
            'sales_invoice',
            (string) $invoice->getKey(),
            $effectType,
        );
    }

    private function lineIdentity(SalesInvoiceLine $line, string $effectType): SourceEffectIdentity
    {
        return new SourceEffectIdentity(
            (int) $line->company_id,
            'sales_invoice_line',
            (string) $line->getKey(),
            $effectType,
        );
    }
}
