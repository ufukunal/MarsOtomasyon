<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class FinalizeSalesInvoice
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AccountTransactionPoster $accountTransactions,
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

            if ($invoice->statusEnum() === SalesInvoiceStatus::Finalized) {
                return $invoice;
            }

            if ($invoice->statusEnum() !== SalesInvoiceStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Yalnız taslak satış faturası kesinleştirilebilir.',
                ]);
            }

            if ((string) $invoice->gross_total === '0.000000') {
                throw ValidationException::withMessages([
                    'gross_total' => 'Genel toplamı sıfır olan satış faturası kesinleştirilemez.',
                ]);
            }

            $account = Account::query()
                ->where('company_id', $companyId)
                ->whereKey($invoice->account_id)
                ->lockForUpdate()
                ->first();

            if (! $account instanceof Account) {
                throw ValidationException::withMessages([
                    'account_id' => 'Satış faturası carisi aktif şirkette bulunamadı.',
                ]);
            }

            if ((string) $invoice->currency_code !== (string) $account->book_currency_code) {
                throw ValidationException::withMessages([
                    'currency_code' => 'Cari ledger posting için fatura para birimi cari defter para birimiyle aynı olmalıdır.',
                ]);
            }

            $this->accountTransactions->post(new PostAccountTransactionData(
                accountId: (int) $invoice->account_id,
                postingDate: $invoice->invoice_date->format('Y-m-d'),
                signedAmount: (string) $invoice->gross_total,
                sourceEffect: $this->identity($invoice, 'account.sales_invoice'),
                memo: 'Satış faturası '.$invoice->number,
            ));

            $invoice->forceFill([
                'status' => SalesInvoiceStatus::Finalized,
                'finalized_at' => $this->clock->now(),
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
}
