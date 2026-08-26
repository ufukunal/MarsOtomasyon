<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use App\Modules\Dispatches\Enums\DispatchStatus;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Models\DispatchLine;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Models\Product;
use App\Modules\SalesInvoices\Enums\SalesInvoiceMode;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class CreateSalesInvoice
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private DocumentNumberIssuer $numbers,
    ) {}

    public function handle(SalesInvoiceDraftData $data, string $seriesCode = 'default'): SalesInvoice
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $seriesCode = mb_strtolower(trim($seriesCode));
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $seriesCode) !== 1 || strlen($seriesCode) > 64) {
            throw ValidationException::withMessages([
                'series_code' => 'Fatura numara serisi canonical ve en fazla 64 karakter olmalıdır.',
            ]);
        }
        if ($data->lines === []) {
            throw ValidationException::withMessages(['lines' => 'Satış faturası en az bir satır içermelidir.']);
        }

        try {
            return DB::transaction(function () use ($companyId, $data, $seriesCode): SalesInvoice {
                [$account, $order, $dispatch, $currencyCode] = $this->resolveHeaderSource($companyId, $data);
                $address = $this->billingAddress($companyId, (int) $account->getKey(), $data->sourceBillingAddressId);
                $lines = $this->resolveLines($companyId, $data, $order, $dispatch);
                $this->assertActiveAllocations($companyId, $lines);

                $taxIdentityType = $account->getRawOriginal('tax_identity_type');
                if (! is_string($taxIdentityType)) {
                    throw new LogicException('Cari vergi kimlik tipi snapshot değeri geçersiz.');
                }

                $number = $this->numbers->issue($companyId, DocumentType::SalesInvoice, $seriesCode);
                $invoice = SalesInvoice::query()->create([
                    'company_id' => $companyId,
                    'account_id' => $account->getKey(),
                    'source_billing_address_id' => $address->getKey(),
                    'source_sales_order_id' => $order?->getKey(),
                    'source_dispatch_id' => $dispatch?->getKey(),
                    'number' => $number->number,
                    'series_code' => $number->seriesCode,
                    'sequence_value' => $number->sequenceValue,
                    'mode' => $data->mode,
                    'status' => SalesInvoiceStatus::Draft,
                    'invoice_date' => $data->invoiceDate,
                    'currency_code' => $currencyCode,
                    'customer_legal_name' => $account->legal_name,
                    'customer_trade_name' => $account->trade_name,
                    'customer_tax_identity_type' => $taxIdentityType,
                    'customer_tax_number' => $account->tax_number,
                    'customer_tax_office' => $account->tax_office,
                    'recipient_name' => $address->recipient_name,
                    'address_line1' => $address->line1,
                    'address_line2' => $address->line2,
                    'district' => $address->district,
                    'city' => $address->city,
                    'postal_code' => $address->postal_code,
                    'country_code' => strtoupper((string) $address->country_code),
                    'note' => $this->nullableTrimmed($data->note),
                ]);

                foreach ($lines as $index => $line) {
                    $invoice->lines()->create([
                        'company_id' => $companyId,
                        'source_sales_order_id' => $line['source_sales_order_id'],
                        'source_sales_order_line_id' => $line['source_sales_order_line_id'],
                        'source_dispatch_id' => $line['source_dispatch_id'],
                        'source_dispatch_line_id' => $line['source_dispatch_line_id'],
                        'position' => $index + 1,
                        'product_id' => $line['product_id'],
                        'warehouse_id' => $line['warehouse_id'],
                        'location_id' => $line['location_id'],
                        'product_code' => $line['product_code'],
                        'product_name' => $line['product_name'],
                        'description' => $line['description'],
                        'quantity' => $line['quantity'],
                    ]);
                }

                return $invoice->load('lines');
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['series_code' => $exception->getMessage()]);
        }
    }

    /** @return array{Account,?SalesOrder,?Dispatch,string} */
    private function resolveHeaderSource(int $companyId, SalesInvoiceDraftData $data): array
    {
        return match ($data->mode) {
            SalesInvoiceMode::Direct => $this->resolveDirectHeader($companyId, $data),
            SalesInvoiceMode::OrderLinked => $this->resolveOrderHeader($companyId, $data),
            SalesInvoiceMode::DispatchLinked => $this->resolveDispatchHeader($companyId, $data),
        };
    }

    /** @return array{Account,null,null,string} */
    private function resolveDirectHeader(int $companyId, SalesInvoiceDraftData $data): array
    {
        if ($data->accountId === null || $data->salesOrderId !== null || $data->dispatchId !== null) {
            throw ValidationException::withMessages([
                'mode' => 'Doğrudan fatura yalnız cari kaynağı taşır; sipariş/irsaliye kaynağı taşıyamaz.',
            ]);
        }

        $account = $this->activeCustomer($companyId, $data->accountId);

        return [$account, null, null, (string) $account->book_currency_code];
    }

    /** @return array{Account,SalesOrder,null,string} */
    private function resolveOrderHeader(int $companyId, SalesInvoiceDraftData $data): array
    {
        if ($data->accountId !== null || $data->salesOrderId === null || $data->dispatchId !== null) {
            throw ValidationException::withMessages([
                'mode' => 'Sipariş bağlı fatura yalnız satış siparişi kaynağı taşımalıdır.',
            ]);
        }

        $order = SalesOrder::query()
            ->where('company_id', $companyId)
            ->whereKey($data->salesOrderId)
            ->lockForUpdate()
            ->first();
        if (! $order instanceof SalesOrder) {
            throw ValidationException::withMessages(['sales_order_id' => 'Satış siparişi aktif şirkete ait değildir.']);
        }
        $account = $this->activeCustomer($companyId, (int) $order->account_id);

        return [$account, $order, null, (string) $order->currency_code];
    }

    /** @return array{Account,SalesOrder,Dispatch,string} */
    private function resolveDispatchHeader(int $companyId, SalesInvoiceDraftData $data): array
    {
        if ($data->accountId !== null || $data->salesOrderId !== null || $data->dispatchId === null) {
            throw ValidationException::withMessages([
                'mode' => 'İrsaliye bağlı fatura yalnız kesinleşmiş irsaliye kaynağı taşımalıdır.',
            ]);
        }

        $dispatch = Dispatch::query()
            ->where('company_id', $companyId)
            ->whereKey($data->dispatchId)
            ->lockForUpdate()
            ->first();
        if (! $dispatch instanceof Dispatch || $dispatch->statusEnum() !== DispatchStatus::Finalized) {
            throw ValidationException::withMessages([
                'dispatch_id' => 'İrsaliye bağlı fatura için aktif şirkete ait kesinleşmiş irsaliye seçilmelidir.',
            ]);
        }

        $order = SalesOrder::query()
            ->where('company_id', $companyId)
            ->whereKey($dispatch->sales_order_id)
            ->lockForUpdate()
            ->first();
        if (! $order instanceof SalesOrder || (int) $order->account_id !== (int) $dispatch->account_id) {
            throw new LogicException('İrsaliye satış siparişi lineage değeri geçersiz.');
        }
        $account = $this->activeCustomer($companyId, (int) $dispatch->account_id);

        return [$account, $order, $dispatch, (string) $order->currency_code];
    }

    private function activeCustomer(int $companyId, int $accountId): Account
    {
        $account = Account::query()
            ->where('company_id', $companyId)
            ->whereKey($accountId)
            ->lockForUpdate()
            ->first();

        if (! $account instanceof Account
            || ! $account->isActive()
            || ! in_array($account->typeEnum(), [AccountType::Customer, AccountType::Mixed], true)) {
            throw ValidationException::withMessages([
                'account_id' => 'Satış faturası için aktif müşteri veya karma cari seçilmelidir.',
            ]);
        }

        return $account;
    }

    private function billingAddress(int $companyId, int $accountId, int $addressId): AccountAddress
    {
        $address = AccountAddress::query()
            ->where('company_id', $companyId)
            ->where('account_id', $accountId)
            ->where('type', AccountAddressType::Billing->value)
            ->whereKey($addressId)
            ->first();

        if (! $address instanceof AccountAddress) {
            throw ValidationException::withMessages([
                'source_billing_address_id' => 'Fatura adresi seçilen satış carisine ait olmalıdır.',
            ]);
        }

        return $address;
    }

    /**
     * @return list<array{
     *   source_sales_order_id:?int,source_sales_order_line_id:?int,source_dispatch_id:?int,source_dispatch_line_id:?int,
     *   product_id:int,warehouse_id:int,location_id:int,product_code:string,product_name:string,description:?string,quantity:string
     * }>
     */
    private function resolveLines(
        int $companyId,
        SalesInvoiceDraftData $data,
        ?SalesOrder $order,
        ?Dispatch $dispatch,
    ): array {
        return match ($data->mode) {
            SalesInvoiceMode::Direct => $this->resolveDirectLines($companyId, $data->lines),
            SalesInvoiceMode::OrderLinked => $this->resolveOrderLines($companyId, $order, $data->lines),
            SalesInvoiceMode::DispatchLinked => $this->resolveDispatchLines($companyId, $order, $dispatch, $data->lines),
        };
    }

    /** @param list<SalesInvoiceLineData> $lines */
    private function resolveDirectLines(int $companyId, array $lines): array
    {
        $resolved = [];
        foreach ($lines as $index => $line) {
            if ($line->productId === null || $line->salesOrderLineId !== null || $line->dispatchLineId !== null) {
                throw ValidationException::withMessages([
                    "lines.$index" => 'Doğrudan fatura satırı yalnız ürün kaynağı taşımalıdır.',
                ]);
            }
            [$warehouseId, $locationId] = $this->requiredAllocation($line, $index);
            $product = Product::query()
                ->where('company_id', $companyId)
                ->whereKey($line->productId)
                ->first();
            if (! $product instanceof Product || ! $product->isActive()) {
                throw ValidationException::withMessages(["lines.$index.product_id" => 'Aktif şirkete ait aktif ürün seçilmelidir.']);
            }

            $resolved[] = $this->resolvedLine(
                null,
                null,
                null,
                null,
                (int) $product->getKey(),
                $warehouseId,
                $locationId,
                (string) $product->code,
                (string) $product->name,
                null,
                $this->positiveDecimal($line->quantity, $index),
            );
        }

        return $resolved;
    }

    /** @param list<SalesInvoiceLineData> $lines */
    private function resolveOrderLines(int $companyId, ?SalesOrder $order, array $lines): array
    {
        if (! $order instanceof SalesOrder) {
            throw new LogicException('Sipariş bağlı fatura source siparişi çözümlenemedi.');
        }

        $ids = [];
        foreach ($lines as $index => $line) {
            if ($line->productId !== null || $line->salesOrderLineId === null || $line->dispatchLineId !== null) {
                throw ValidationException::withMessages(["lines.$index" => 'Sipariş bağlı fatura satırı yalnız sipariş satırı kaynağı taşımalıdır.']);
            }
            if (isset($ids[$line->salesOrderLineId])) {
                throw ValidationException::withMessages(['lines' => 'Aynı sipariş satırı bir faturada yalnız bir kez seçilebilir.']);
            }
            $ids[$line->salesOrderLineId] = true;
        }

        $sources = SalesOrderLine::query()
            ->where('company_id', $companyId)
            ->where('sales_order_id', $order->getKey())
            ->whereIn('id', array_keys($ids))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($sources->count() !== count($ids)) {
            throw ValidationException::withMessages(['lines' => 'Fatura satırlarının tamamı seçilen satış siparişine ait olmalıdır.']);
        }

        $resolved = [];
        foreach ($lines as $index => $line) {
            $source = $sources->get($line->salesOrderLineId);
            if (! $source instanceof SalesOrderLine) {
                throw new LogicException('Sipariş satırı çözümlemesi tutarsız.');
            }
            [$warehouseId, $locationId] = $this->inheritOrRequireAllocation($source, $line, $index);
            $resolved[] = $this->resolvedLine(
                (int) $order->getKey(),
                (int) $source->getKey(),
                null,
                null,
                (int) $source->product_id,
                $warehouseId,
                $locationId,
                (string) $source->product_code,
                (string) $source->product_name,
                $this->nullableTrimmed($source->description),
                $this->positiveDecimal($line->quantity, $index),
            );
        }

        return $resolved;
    }

    /** @param list<SalesInvoiceLineData> $lines */
    private function resolveDispatchLines(
        int $companyId,
        ?SalesOrder $order,
        ?Dispatch $dispatch,
        array $lines,
    ): array {
        if (! $order instanceof SalesOrder || ! $dispatch instanceof Dispatch) {
            throw new LogicException('İrsaliye bağlı fatura source lineage çözümlenemedi.');
        }

        $ids = [];
        foreach ($lines as $index => $line) {
            if ($line->productId !== null || $line->salesOrderLineId !== null || $line->dispatchLineId === null) {
                throw ValidationException::withMessages(["lines.$index" => 'İrsaliye bağlı fatura satırı yalnız irsaliye satırı kaynağı taşımalıdır.']);
            }
            if (isset($ids[$line->dispatchLineId])) {
                throw ValidationException::withMessages(['lines' => 'Aynı irsaliye satırı bir faturada yalnız bir kez seçilebilir.']);
            }
            $ids[$line->dispatchLineId] = true;
        }

        $sources = DispatchLine::query()
            ->where('company_id', $companyId)
            ->where('dispatch_id', $dispatch->getKey())
            ->where('sales_order_id', $order->getKey())
            ->whereIn('id', array_keys($ids))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($sources->count() !== count($ids)) {
            throw ValidationException::withMessages(['lines' => 'Fatura satırlarının tamamı seçilen kesinleşmiş irsaliyeye ait olmalıdır.']);
        }

        $resolved = [];
        foreach ($lines as $index => $line) {
            $source = $sources->get($line->dispatchLineId);
            if (! $source instanceof DispatchLine) {
                throw new LogicException('İrsaliye satırı çözümlemesi tutarsız.');
            }
            if (($line->warehouseId !== null && $line->warehouseId !== (int) $source->warehouse_id)
                || ($line->locationId !== null && $line->locationId !== (int) $source->location_id)) {
                throw ValidationException::withMessages([
                    "lines.$index.allocation_key" => 'İrsaliye bağlı faturada depo/konum source irsaliyeden değiştirilemez.',
                ]);
            }
            $resolved[] = $this->resolvedLine(
                (int) $order->getKey(),
                (int) $source->sales_order_line_id,
                (int) $dispatch->getKey(),
                (int) $source->getKey(),
                (int) $source->product_id,
                (int) $source->warehouse_id,
                (int) $source->location_id,
                (string) $source->product_code,
                (string) $source->product_name,
                $this->nullableTrimmed($source->description),
                $this->positiveDecimal($line->quantity, $index),
            );
        }

        return $resolved;
    }

    /** @return array{0:int,1:int} */
    private function inheritOrRequireAllocation(SalesOrderLine $source, SalesInvoiceLineData $line, int $index): array
    {
        $sourceWarehouse = $source->warehouse_id === null ? null : (int) $source->warehouse_id;
        $sourceLocation = $source->location_id === null ? null : (int) $source->location_id;
        if (($sourceWarehouse === null) !== ($sourceLocation === null)) {
            throw new LogicException('Sipariş satırı depo/konum allocation değeri tutarsız.');
        }
        if ($sourceWarehouse !== null && $sourceLocation !== null) {
            if (($line->warehouseId !== null && $line->warehouseId !== $sourceWarehouse)
                || ($line->locationId !== null && $line->locationId !== $sourceLocation)) {
                throw ValidationException::withMessages([
                    "lines.$index.allocation_key" => 'Sipariş allocation değeri faturada değiştirilemez.',
                ]);
            }

            return [$sourceWarehouse, $sourceLocation];
        }

        return $this->requiredAllocation($line, $index);
    }

    /** @return array{0:int,1:int} */
    private function requiredAllocation(SalesInvoiceLineData $line, int $index): array
    {
        if ($line->warehouseId === null || $line->locationId === null) {
            throw ValidationException::withMessages([
                "lines.$index.allocation_key" => 'Fatura satırı için depo ve konum birlikte seçilmelidir.',
            ]);
        }

        return [$line->warehouseId, $line->locationId];
    }

    /** @param list<array<string,mixed>> $lines */
    private function assertActiveAllocations(int $companyId, array $lines): void
    {
        $warehouseIds = [];
        $pairs = [];
        foreach ($lines as $line) {
            $warehouseIds[(int) $line['warehouse_id']] = true;
            $pairs[(int) $line['warehouse_id'].':'.(int) $line['location_id']] = true;
        }

        $activeWarehouses = Warehouse::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', array_keys($warehouseIds))
            ->pluck('id')
            ->mapWithKeys(static fn ($id): array => [(int) $id => true])
            ->all();

        $activePairs = [];
        foreach (WarehouseLocation::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('warehouse_id', array_keys($warehouseIds))
            ->get(['id', 'warehouse_id']) as $location) {
            $activePairs[(int) $location->warehouse_id.':'.(int) $location->getKey()] = true;
        }

        foreach (array_keys($pairs) as $pair) {
            [$warehouseId] = explode(':', $pair, 2);
            if (! isset($activeWarehouses[(int) $warehouseId]) || ! isset($activePairs[$pair])) {
                throw ValidationException::withMessages([
                    'lines' => 'Fatura satırı depo/konumu aktif şirkete ait ve aktif olmalıdır.',
                ]);
            }
        }
    }

    /** @return array<string,mixed> */
    private function resolvedLine(
        ?int $sourceSalesOrderId,
        ?int $sourceSalesOrderLineId,
        ?int $sourceDispatchId,
        ?int $sourceDispatchLineId,
        int $productId,
        int $warehouseId,
        int $locationId,
        string $productCode,
        string $productName,
        ?string $description,
        string $quantity,
    ): array {
        return [
            'source_sales_order_id' => $sourceSalesOrderId,
            'source_sales_order_line_id' => $sourceSalesOrderLineId,
            'source_dispatch_id' => $sourceDispatchId,
            'source_dispatch_line_id' => $sourceDispatchLineId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'product_code' => $productCode,
            'product_name' => $productName,
            'description' => $description,
            'quantity' => $quantity,
        ];
    }

    private function positiveDecimal(string $value, int $index): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages([
                "lines.$index.quantity" => 'Fatura miktarı pozitif ve en fazla 6 ondalıklı geçerli bir sayı olmalıdır.',
            ]);
        }
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        if (strlen($integer) > 14) {
            throw ValidationException::withMessages(["lines.$index.quantity" => 'Fatura miktarı desteklenen sayısal sınırı aşıyor.']);
        }
        $fraction = str_pad($fraction, 6, '0');
        if ($integer === '0' && $fraction === '000000') {
            throw ValidationException::withMessages(["lines.$index.quantity" => 'Fatura miktarı sıfırdan büyük olmalıdır.']);
        }

        return $integer.'.'.$fraction;
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
