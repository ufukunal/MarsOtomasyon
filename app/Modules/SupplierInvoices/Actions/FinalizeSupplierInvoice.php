<?php

namespace App\Modules\SupplierInvoices\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Ledger\AccountAmountNormalizer;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderProgressType;
use App\Modules\PurchaseOrders\Progress\PurchaseOrderProgressService;
use App\Modules\SupplierInvoices\Enums\SupplierInvoiceStatus;
use App\Modules\SupplierInvoices\Models\SupplierInvoice;
use App\Modules\SupplierInvoices\Models\SupplierInvoiceLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class FinalizeSupplierInvoice
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AccountTransactionPoster $accountTransactions,
        private AccountAmountNormalizer $amounts,
        private PurchaseOrderProgressService $progress,
        private Clock $clock,
    ) {}

    public function handle(int $supplierInvoiceId): SupplierInvoice
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $supplierInvoiceId): SupplierInvoice {
            $invoice = SupplierInvoice::query()
                ->where('company_id', $companyId)
                ->whereKey($supplierInvoiceId)
                ->lockForUpdate()
                ->first();
            if (! $invoice instanceof SupplierInvoice) {
                throw ValidationException::withMessages(['supplier_invoice' => 'Alış faturası aktif şirkette bulunamadı.']);
            }
            if ($invoice->statusEnum() === SupplierInvoiceStatus::Finalized) {
                return $invoice;
            }

            $lines = $invoice->lines()->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw new LogicException('Kesinleştirilecek alış faturası satırsız olamaz.');
            }
            if ((string) $invoice->gross_total === '0.000000') {
                throw ValidationException::withMessages(['gross_total' => 'Genel toplamı sıfır olan alış faturası kesinleştirilemez.']);
            }

            $account = Account::query()
                ->where('company_id', $companyId)
                ->whereKey($invoice->account_id)
                ->lockForUpdate()
                ->first();
            if (! $account instanceof Account) {
                throw ValidationException::withMessages(['account_id' => 'Alış faturası tedarikçi carisi aktif şirkette bulunamadı.']);
            }
            if ((string) $invoice->currency_code !== (string) $account->book_currency_code) {
                throw ValidationException::withMessages(['currency_code' => 'Cari ledger posting için fatura para birimi cari defter para birimiyle aynı olmalıdır.']);
            }

            $postingDate = $invoice->getRawOriginal('invoice_date');
            if (! is_string($postingDate)) {
                throw ValidationException::withMessages(['invoice_date' => 'Alış faturası belge tarihi geçersiz.']);
            }

            $this->accountTransactions->post(new PostAccountTransactionData(
                accountId: (int) $invoice->account_id,
                postingDate: $postingDate,
                signedAmount: $this->amounts->negate((string) $invoice->gross_total),
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'supplier_invoice',
                    (string) $invoice->getKey(),
                    'account.supplier_invoice',
                ),
                memo: 'Alış faturası '.$invoice->number,
            ));

            /** @var SupplierInvoiceLine $line */
            foreach ($lines as $line) {
                $this->progress->record(
                    new SourceEffectIdentity(
                        $companyId,
                        'supplier_invoice_line',
                        (string) $line->getKey(),
                        'progress.invoice',
                    ),
                    (int) $line->purchase_order_line_id,
                    PurchaseOrderProgressType::Invoiced,
                    (string) $line->quantity,
                );
            }

            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::Finalized,
                'finalized_at' => $this->clock->now(),
            ])->save();

            return $invoice->refresh();
        });
    }
}
