<?php

namespace App\Modules\Dispatches\Actions;

use App\Modules\Accounts\Enums\AccountAddressType;
use App\Modules\Accounts\Models\AccountAddress;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use App\Modules\Dispatches\Enums\DispatchStatus;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
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

                $allocations = $this->resolveAllocations($companyId, $data->lines, $orderLines);

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
                    $allocation = $allocations[$lineData->salesOrderLineId];
                    $dispatch->lines()->create([
                        'company_id' => $companyId,
                        'sales_order_id' => $order->getKey(),
                        'sales_order_line_id' => $source->getKey(),
                        'position' => $index + 1,
                        'product_id' => $source->product_id,
                        'warehouse_id' => $allocation['warehouse_id'],
                        'location_id' => $allocation['location_id'],
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

    /**
     * @param list<DispatchLineData> $lines
     * @param array<int, SalesOrderLine> $orderLines
     * @return array<int, array{warehouse_id:int,location_id:int}>
     */
    private function resolveAllocations(int $companyId, array $lines, array $orderLines): array
    {
        $allocations = [];
        $warehouseIds = [];
        $locationIds = [];

        foreach ($lines as $index => $lineData) {
            $source = $orderLines[$lineData->salesOrderLineId];
            $sourceWarehouseId = $source->warehouse_id === null ? null : (int) $source->warehouse_id;
            $sourceLocationId = $source->location_id === null ? null : (int) $source->location_id;

            if (($lineData->warehouseId === null) !== ($lineData->locationId === null)) {
                throw ValidationException::withMessages([
                    "lines.$index.allocation_key" => 'Depo ve konum birlikte seçilmelidir.',
                ]);
            }

            if ($sourceWarehouseId !== null && $sourceLocationId !== null) {
                if (($lineData->warehouseId !== null && $lineData->warehouseId !== $sourceWarehouseId)
                    || ($lineData->locationId !== null && $lineData->locationId !== $sourceLocationId)) {
                    throw ValidationException::withMessages([
                        "lines.$index.allocation_key" => 'Rezerve sipariş satırının depo/konum allocation değeri irsaliyede değiştirilemez.',
                    ]);
                }

                $warehouseId = $sourceWarehouseId;
                $locationId = $sourceLocationId;
            } else {
                if ($lineData->warehouseId === null || $lineData->locationId === null) {
                    throw ValidationException::withMessages([
                        "lines.$index.allocation_key" => 'Depo allocation değeri olmayan sipariş satırı için sevk depo/konumu seçilmelidir.',
                    ]);
                }

                $warehouseId = $lineData->warehouseId;
                $locationId = $lineData->locationId;
            }

            $allocations[$lineData->salesOrderLineId] = [
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
            ];
            $warehouseIds[$warehouseId] = true;
            $locationIds[$locationId] = true;
        }

        $activeWarehouses = [];
        foreach (Warehouse::query()
            ->where('company_id', $companyId)
            ->whereIn('id', array_keys($warehouseIds))
            ->where('is_active', true)
            ->get(['id']) as $warehouse) {
            $activeWarehouses[(int) $warehouse->getKey()] = true;
        }

        $activeLocations = [];
        foreach (WarehouseLocation::query()
            ->where('company_id', $companyId)
            ->whereIn('warehouse_id', array_keys($warehouseIds))
            ->whereIn('id', array_keys($locationIds))
            ->where('is_active', true)
            ->get(['id', 'warehouse_id']) as $location) {
            $activeLocations[(int) $location->warehouse_id.':'.(int) $location->getKey()] = true;
        }

        foreach ($allocations as $lineId => $allocation) {
            if (! isset($activeWarehouses[$allocation['warehouse_id']])
                || ! isset($activeLocations[$allocation['warehouse_id'].':'.$allocation['location_id']])) {
                throw ValidationException::withMessages([
                    'lines' => "Sipariş satırı #$lineId için seçilen depo/konum aktif şirkete ait ve aktif olmalıdır.",
                ]);
            }
        }

        return $allocations;
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
