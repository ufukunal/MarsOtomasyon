<?php

namespace App\Modules\PurchaseReturns\Actions;

use App\Foundation\Clock\Clock;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Ledger\AccountTransactionPoster;
use App\Modules\Accounts\Ledger\PostAccountTransactionData;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptStatus;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\GoodsReceipts\Models\GoodsReceiptLine;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\PurchaseReturns\Enums\PurchaseReturnStatus;
use App\Modules\PurchaseReturns\Models\PurchaseReturn;
use App\Modules\PurchaseReturns\Models\PurchaseReturnLine;
use App\Modules\SupplierInvoices\Enums\SupplierInvoiceStatus;
use App\Modules\SupplierInvoices\Models\SupplierInvoice;
use App\Modules\SupplierInvoices\Models\SupplierInvoiceLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class FinalizePurchaseReturn
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AccountTransactionPoster $accountTransactions,
        private StockMovementPoster $stockMovements,
        private Clock $clock,
    ) {}

    public function handle(int $purchaseReturnId): PurchaseReturn
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $purchaseReturnId): PurchaseReturn {
            $purchaseReturn = PurchaseReturn::query()
                ->where('company_id', $companyId)
                ->whereKey($purchaseReturnId)
                ->lockForUpdate()
                ->first();
            if (! $purchaseReturn instanceof PurchaseReturn) {
                throw ValidationException::withMessages(['purchase_return' => 'Satınalma iadesi aktif şirkette bulunamadı.']);
            }
            if ($purchaseReturn->statusEnum() === PurchaseReturnStatus::Finalized) {
                return $purchaseReturn;
            }

            $lines = $purchaseReturn->lines()->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw new LogicException('Kesinleştirilecek satınalma iadesi satırsız olamaz.');
            }
            if ((string) $purchaseReturn->gross_total === '0.000000') {
                throw ValidationException::withMessages(['gross_total' => 'Genel toplamı sıfır olan satınalma iadesi kesinleştirilemez.']);
            }

            $account = Account::query()
                ->where('company_id', $companyId)
                ->whereKey($purchaseReturn->account_id)
                ->lockForUpdate()
                ->first();
            if (! $account instanceof Account) {
                throw ValidationException::withMessages(['account_id' => 'İade tedarikçi carisi aktif şirkette bulunamadı.']);
            }
            if ((string) $purchaseReturn->currency_code !== (string) $account->book_currency_code) {
                throw ValidationException::withMessages(['currency_code' => 'Cari ledger posting için iade para birimi cari defter para birimiyle aynı olmalıdır.']);
            }

            $this->assertEligibility($companyId, $purchaseReturn, $lines);

            $postingDate = $purchaseReturn->getRawOriginal('return_date');
            if (! is_string($postingDate)) {
                throw ValidationException::withMessages(['return_date' => 'Satınalma iadesi belge tarihi geçersiz.']);
            }

            $this->accountTransactions->post(new PostAccountTransactionData(
                accountId: (int) $purchaseReturn->account_id,
                postingDate: $postingDate,
                signedAmount: (string) $purchaseReturn->gross_total,
                sourceEffect: new SourceEffectIdentity(
                    $companyId,
                    'purchase_return',
                    (string) $purchaseReturn->getKey(),
                    'account.purchase_return',
                ),
                memo: 'Satınalma iadesi '.$purchaseReturn->number,
            ));

            /** @var PurchaseReturnLine $line */
            foreach ($lines as $line) {
                $this->stockMovements->post(new PostStockMovementData(
                    sourceEffect: new SourceEffectIdentity(
                        $companyId,
                        'purchase_return_line',
                        (string) $line->getKey(),
                        'stock.out',
                    ),
                    productId: (int) $line->product_id,
                    warehouseId: (int) $line->warehouse_id,
                    locationId: (int) $line->location_id,
                    movementType: StockMovementType::PurchaseReturnOut,
                    quantity: (string) $line->quantity,
                    note: 'Satınalma iadesi '.$purchaseReturn->number,
                ));
            }

            $purchaseReturn->forceFill([
                'status' => PurchaseReturnStatus::Finalized,
                'finalized_at' => $this->clock->now(),
            ])->save();

            return $purchaseReturn->refresh();
        });
    }

    /** @param Collection<int, PurchaseReturnLine> $lines */
    private function assertEligibility(int $companyId, PurchaseReturn $purchaseReturn, $lines): void
    {
        $physical = [];
        $financial = [];

        /** @var PurchaseReturnLine $line */
        foreach ($lines as $line) {
            $receiptLine = GoodsReceiptLine::query()
                ->where('company_id', $companyId)
                ->whereKey($line->goods_receipt_line_id)
                ->lockForUpdate()
                ->first();
            $invoiceLine = SupplierInvoiceLine::query()
                ->where('company_id', $companyId)
                ->whereKey($line->supplier_invoice_line_id)
                ->lockForUpdate()
                ->first();
            if (! $receiptLine instanceof GoodsReceiptLine || ! $invoiceLine instanceof SupplierInvoiceLine) {
                throw ValidationException::withMessages(['lines' => 'İade kaynak lineage satırlarından biri bulunamadı.']);
            }

            $receipt = GoodsReceipt::query()
                ->where('company_id', $companyId)
                ->whereKey($receiptLine->goods_receipt_id)
                ->lockForUpdate()
                ->first();
            $invoice = SupplierInvoice::query()
                ->where('company_id', $companyId)
                ->whereKey($invoiceLine->supplier_invoice_id)
                ->lockForUpdate()
                ->first();
            if (! $receipt instanceof GoodsReceipt || $receipt->statusEnum() !== GoodsReceiptStatus::Finalized
                || ! $invoice instanceof SupplierInvoice || $invoice->statusEnum() !== SupplierInvoiceStatus::Finalized) {
                throw ValidationException::withMessages(['lines' => 'Satınalma iadesi yalnız kesinleşmiş mal kabul ve alış faturası lineage üzerinden yapılabilir.']);
            }
            if ((int) $receiptLine->purchase_order_id !== (int) $purchaseReturn->purchase_order_id
                || (int) $invoiceLine->purchase_order_id !== (int) $purchaseReturn->purchase_order_id
                || (int) $receiptLine->purchase_order_line_id !== (int) $invoiceLine->purchase_order_line_id
                || (int) $receiptLine->product_id !== (int) $invoiceLine->product_id) {
                throw ValidationException::withMessages(['lines' => 'İade fiziksel ve finansal lineage eşleşmesi bozulmuş.']);
            }

            $physical[(int) $receiptLine->getKey()] = true;
            $financial[(int) $invoiceLine->getKey()] = true;
        }

        foreach (array_keys($physical) as $sourceId) {
            $accepted = DB::table('goods_receipt_line_quality')
                ->where('company_id', $companyId)
                ->where('goods_receipt_line_id', $sourceId)
                ->value('accepted_quantity');
            $previous = $this->finalizedReturnedQuantity($companyId, 'goods_receipt_line_id', $sourceId);
            $current = $this->currentReturnQuantity((int) $purchaseReturn->getKey(), 'goods_receipt_line_id', $sourceId);
            if ($accepted === null || $this->greaterThan($this->add($previous, $current), (string) $accepted)) {
                throw ValidationException::withMessages(['quantity' => 'İade toplamı kabul edilmiş fiziksel lineage miktarını aşamaz.']);
            }
        }

        foreach (array_keys($financial) as $sourceId) {
            $sourceQuantity = SupplierInvoiceLine::query()
                ->where('company_id', $companyId)
                ->whereKey($sourceId)
                ->value('quantity');
            $previous = $this->finalizedReturnedQuantity($companyId, 'supplier_invoice_line_id', $sourceId);
            $current = $this->currentReturnQuantity((int) $purchaseReturn->getKey(), 'supplier_invoice_line_id', $sourceId);
            if ($sourceQuantity === null || $this->greaterThan($this->add($previous, $current), (string) $sourceQuantity)) {
                throw ValidationException::withMessages(['quantity' => 'İade toplamı finansal alış faturası lineage miktarını aşamaz.']);
            }
        }
    }

    private function finalizedReturnedQuantity(int $companyId, string $column, int $sourceId): string
    {
        $row = DB::table('purchase_return_lines as line')
            ->join('purchase_returns as purchase_return', function ($join): void {
                $join->on('purchase_return.company_id', '=', 'line.company_id')
                    ->on('purchase_return.id', '=', 'line.purchase_return_id');
            })
            ->where('line.company_id', $companyId)
            ->where('line.'.$column, $sourceId)
            ->where('purchase_return.status', 'finalized')
            ->selectRaw('COALESCE(SUM(line.quantity), 0)::numeric(20,6)::text AS quantity')
            ->first();

        return $row === null ? '0.000000' : (string) $row->quantity;
    }

    private function currentReturnQuantity(int $purchaseReturnId, string $column, int $sourceId): string
    {
        $row = DB::table('purchase_return_lines')
            ->where('purchase_return_id', $purchaseReturnId)
            ->where($column, $sourceId)
            ->selectRaw('COALESCE(SUM(quantity), 0)::numeric(20,6)::text AS quantity')
            ->first();

        return $row === null ? '0.000000' : (string) $row->quantity;
    }

    private function add(string $left, string $right): string
    {
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) + CAST(? AS numeric) AS numeric(20,6))::text AS value', [$left, $right]);

        return (string) $row?->value;
    }

    private function greaterThan(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) > CAST(? AS numeric) AS value', [$left, $right]);

        return $row?->value === true;
    }
}
