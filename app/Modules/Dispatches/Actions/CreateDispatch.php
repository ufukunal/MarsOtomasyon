<?php

namespace App\Modules\Dispatches\Actions;

use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use App\Modules\Dispatches\Enums\DispatchStatus;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateDispatch
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private DocumentNumberIssuer $numbers,
    ) {}

    public function handle(DispatchDraftData $data, string $seriesCode = 'default'): Dispatch
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $seriesCode = mb_strtolower(trim($seriesCode));
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $seriesCode) !== 1 || strlen($seriesCode) > 64) {
            throw ValidationException::withMessages(['series_code' => 'İrsaliye numara serisi canonical ve en fazla 64 karakter olmalıdır.']);
        }

        if ($data->lines === []) {
            throw ValidationException::withMessages(['lines' => 'İrsaliye en az bir satır içermelidir.']);
        }

        try {
            return DB::transaction(function () use ($companyId, $seriesCode, $data): Dispatch {
                $order = SalesOrder::query()
                    ->where('company_id', $companyId)
                    ->whereKey($data->salesOrderId)
                    ->lockForUpdate()
                    ->first();
                if (! $order instanceof SalesOrder) {
                    throw ValidationException::withMessages(['sales_order_id' => 'Satış siparişi aktif şirkete ait değildir.']);
                }

                $address = AccountAddress::query()
                    ->where('company_id', $companyId)
                    ->where('account_id', $order->account_id)
                    ->where('type', AccountAddressType::Shipping->value)
                    ->whereKey($data->sourceAddressId)
                    ->first();
                if (! $address instanceof AccountAddress) {
                    throw ValidationException::withMessages(['source_address_id' => 'Sevk adresi sipariş carisine ait değildir.']);
                }

                $lineIds = array_map(static fn (DispatchLineData $line): int => $line->salesOrderLineId, $data->lines);
                if (count($lineIds) !== count(array_unique($lineIds))) {
                    throw ValidationException::withMessages(['lines' => 'Aynı sipariş satırı bir irsaliyede yalnız bir kez seçilebilir.']);
                }

                /** @var array<int, SalesOrderLine> $orderLines */
                $orderLines = [];
                foreach ($order->lines()->whereIn('id', $lineIds)->get() as $orderLine) {
                    $orderLines[(int) $orderLine->getKey()] = $orderLine;
                }
                if (count($orderLines) !== count($lineIds)) {
                    throw ValidationException::withMessages(['lines' => 'İrsaliye satırlarının tamamı seçilen satış siparişine ait olmalıdır.']);
                }

                $number = $this->numbers->issue($companyId, DocumentType::Dispatch, $seriesCode);
                $dispatch = Dispatch::query()->create([
                    'company_id' => $companyId,
                    'account_id' => $order->account_id,
                    'sales_order_id' => $order->getKey(),
                    'source_address_id' => $address->getKey(),
                    'number' => $number->number,
                    'series_code' => $number->seriesCode,
                    'sequence_value' => $number->sequenceValue,
                    'status' => DispatchStatus::Draft,
                    'dispatch_date' => $data->dispatchDate,
                    'recipient_name' => $address->recipient_name,
                    'address_line1' => $address->line1,
                    'address_line2' => $address->line2,
                    'district' => $address->district,
                    'city' => $address->city,
                    'postal_code' => $address->postal_code,
                    'country_code' => strtoupper((string) $address->country_code),
                    'carrier_name' => $this->nullableTrimmed($data->carrierName),
                    'carrier_service' => $this->nullableTrimmed($data->carrierService),
                    'tracking_number' => $this->nullableTrimmed($data->trackingNumber),
                    'note' => $this->nullableTrimmed($data->note),
                ]);

                foreach ($data->lines as $index => $lineData) {
                    $source = $orderLines[$lineData->salesOrderLineId];
                    $dispatch->lines()->create([
                        'company_id' => $companyId,
                        'sales_order_id' => $order->getKey(),
                        'sales_order_line_id' => $source->getKey(),
                        'position' => $index + 1,
                        'product_id' => $source->product_id,
                        'warehouse_id' => $source->warehouse_id,
                        'location_id' => $source->location_id,
                        'product_code' => $source->product_code,
                        'product_name' => $source->product_name,
                        'description' => $source->description,
                        'quantity' => $lineData->quantity,
                    ]);
                }

                return $dispatch->load('lines');
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['series_code' => $exception->getMessage()]);
        }
    }

    private function nullableTrimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
