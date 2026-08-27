<?php

namespace App\Modules\GoodsReceipts\Actions;

use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLine;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class GoodsReceiptDraftResolver
{
    public function resolve(int $companyId, GoodsReceiptDraftData $data): ResolvedGoodsReceiptDraft
    {
        if ($data->lines === [] || count($data->lines) > 200) {
            throw ValidationException::withMessages([
                'lines' => 'Mal kabul 1 ile 200 arasında satır içermelidir.',
            ]);
        }

        $receiptDate = $this->date($data->receiptDate);
        $note = $this->nullableText($data->note, 5000, 'note');

        $order = PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->whereKey($data->purchaseOrderId)
            ->lockForUpdate()
            ->first();

        if (! $order instanceof PurchaseOrder) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Satınalma siparişi aktif şirkette bulunamadı.',
            ]);
        }

        $resolved = [];
        foreach ($data->lines as $index => $lineData) {
            $line = PurchaseOrderLine::query()
                ->where('company_id', $companyId)
                ->where('purchase_order_id', $order->getKey())
                ->whereKey($lineData->purchaseOrderLineId)
                ->lockForUpdate()
                ->first();

            if (! $line instanceof PurchaseOrderLine) {
                throw ValidationException::withMessages([
                    "lines.$index.purchase_order_line_id" => 'Satınalma siparişi satırı aktif siparişte bulunamadı.',
                ]);
            }

            $location = WarehouseLocation::query()
                ->where('company_id', $companyId)
                ->where('warehouse_id', $lineData->warehouseId)
                ->whereKey($lineData->locationId)
                ->where('is_active', true)
                ->first();

            if (! $location instanceof WarehouseLocation) {
                throw ValidationException::withMessages([
                    "lines.$index.location_id" => 'Mal kabul lokasyonu seçilen aktif depoya ait olmalıdır.',
                ]);
            }

            $received = $this->nonNegativeDecimal($lineData->receivedQuantity, "lines.$index.received_quantity");
            $accepted = $this->nonNegativeDecimal($lineData->acceptedQuantity, "lines.$index.accepted_quantity");
            $pending = $this->nonNegativeDecimal($lineData->pendingQuantity, "lines.$index.pending_quantity");
            $rejected = $this->nonNegativeDecimal($lineData->rejectedQuantity, "lines.$index.rejected_quantity");

            $row = DB::selectOne(
                'SELECT CAST(? AS numeric) > 0 AS received_positive, '
                .'CAST(? AS numeric) + CAST(? AS numeric) + CAST(? AS numeric) = CAST(? AS numeric) AS split_valid, '
                .'CAST((CAST(? AS numeric) / CAST(? AS numeric)) AS numeric(20,6))::text AS unit_cost',
                [$received, $accepted, $pending, $rejected, $received, (string) $line->net_total, (string) $line->quantity],
            );

            if ($row === null) {
                throw ValidationException::withMessages([
                    "lines.$index.received_quantity" => 'Fiziksel teslim miktarı doğrulanamadı.',
                ]);
            }
            if ($row->split_valid !== true) {
                throw ValidationException::withMessages([
                    "lines.$index.accepted_quantity" => 'Kabul + bekleyen kalite + red miktarı fiziksel teslim miktarına eşit olmalıdır.',
                ]);
            }

            if ($row->received_positive !== true) {
                if ($received === '0.000000' && $accepted === '0.000000' && $pending === '0.000000' && $rejected === '0.000000') {
                    continue;
                }

                throw ValidationException::withMessages([
                    "lines.$index.received_quantity" => 'Fiziksel teslim miktarı sıfırdan büyük olmalıdır.',
                ]);
            }

            $unitCost = (string) $row->unit_cost;
            $costCheck = DB::selectOne('SELECT CAST(? AS numeric) > 0 AS positive', [$unitCost]);
            if ($costCheck === null || ($accepted !== '0.000000' && $costCheck->positive !== true)) {
                throw ValidationException::withMessages([
                    "lines.$index.accepted_quantity" => 'Kabul edilen stok için satınalma siparişinden türeyen pozitif provisional birim maliyet gereklidir.',
                ]);
            }

            $remaining = DB::table('purchase_order_line_progress')
                ->where('company_id', $companyId)
                ->where('purchase_order_line_id', $line->getKey())
                ->value('receive_remaining_quantity');
            if (! is_string($remaining) && ! is_numeric($remaining)) {
                throw ValidationException::withMessages([
                    "lines.$index.purchase_order_line_id" => 'Satınalma siparişi receive remaining projection bulunamadı.',
                ]);
            }

            $capacity = DB::selectOne('SELECT CAST(? AS numeric) <= CAST(? AS numeric) AS valid', [$accepted, (string) $remaining]);
            if ($capacity === null || $capacity->valid !== true) {
                throw ValidationException::withMessages([
                    "lines.$index.accepted_quantity" => 'Kabul edilen miktar siparişin kalan kabul miktarını aşamaz.',
                ]);
            }

            $resolved[] = new ResolvedGoodsReceiptLine(
                purchaseOrderLineId: (int) $line->getKey(),
                productId: (int) $line->product_id,
                warehouseId: $lineData->warehouseId,
                locationId: $lineData->locationId,
                productCode: (string) $line->product_code,
                productName: (string) $line->product_name,
                receivedQuantity: $received,
                acceptedQuantity: $accepted,
                pendingQuantity: $pending,
                rejectedQuantity: $rejected,
                provisionalUnitCost: $unitCost,
                note: $this->nullableText($lineData->note, 1000, "lines.$index.note"),
            );
        }

        if ($resolved === []) {
            throw ValidationException::withMessages(['lines' => 'Mal kabul için en az bir satırda fiziksel teslim miktarı girilmelidir.']);
        }

        return new ResolvedGoodsReceiptDraft(
            purchaseOrderId: (int) $order->getKey(),
            accountId: (int) $order->account_id,
            receiptDate: $receiptDate,
            note: $note,
            lines: $resolved,
        );
    }

    private function date(string $value): string
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages(['receipt_date' => 'Mal kabul tarihi Y-m-d formatında olmalıdır.']);
        }

        return $value;
    }

    private function nonNegativeDecimal(string $value, string $field): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages([$field => 'Miktar negatif olmayan ve en fazla 6 ondalıklı geçerli bir sayı olmalıdır.']);
        }

        $integerPart = explode('.', $value, 2)[0];
        if (strlen(ltrim($integerPart, '0')) > 14) {
            throw ValidationException::withMessages([$field => 'Miktar desteklenen sayısal sınırı aşıyor.']);
        }

        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value', [$value]);
        if ($row === null) {
            throw ValidationException::withMessages([$field => 'Miktar normalize edilemedi.']);
        }

        return (string) $row->value;
    }

    private function nullableText(?string $value, int $max, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => "Alan en fazla $max karakter olabilir."]);
        }

        return $value;
    }
}
