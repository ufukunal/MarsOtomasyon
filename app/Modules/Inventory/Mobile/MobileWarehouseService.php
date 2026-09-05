<?php

namespace App\Modules\Inventory\Mobile;

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Dispatches\Actions\FinalizeDispatch;
use App\Modules\Dispatches\Models\Dispatch;
use App\Modules\Dispatches\Models\DispatchLine;
use App\Modules\GoodsReceipts\Actions\FinalizeGoodsReceipt;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\GoodsReceipts\Models\GoodsReceiptLine;
use App\Modules\Inventory\Counts\StockCountService;
use App\Modules\Inventory\Reservations\StockReservationService;
use App\Modules\Inventory\Transfers\WarehouseTransferIssueLineData;
use App\Modules\Inventory\Transfers\WarehouseTransferService;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Product;
use App\Modules\Subcontract\Actions\SubcontractOperations;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class MobileWarehouseService
{
    /** @var list<string> */
    public const OPERATION_TYPES = [
        'goods_receipt.verify',
        'goods_receipt.finalize',
        'picking.consume',
        'dispatch.verify',
        'dispatch.finalize',
        'transfer.issue',
        'transfer.receive',
        'stock_count.start',
        'stock_count.scan',
        'stock_count.post',
        'subcontract.send',
        'subcontract.receive',
    ];

    public function __construct(
        private FinalizeGoodsReceipt $finalizeGoodsReceipt,
        private FinalizeDispatch $finalizeDispatch,
        private StockReservationService $reservations,
        private WarehouseTransferService $transfers,
        private StockCountService $stockCounts,
        private SubcontractOperations $subcontract,
    ) {}

    /** @return array{product_id:int,code:string,name:string,barcode:?string,matched_by:string} */
    public function lookupProduct(int $companyId, string $scan): array
    {
        $scan = trim($scan);
        if ($companyId < 1 || $scan === '') {
            throw ValidationException::withMessages(['scan' => 'Barkod veya ürün kodu zorunludur.']);
        }

        $barcode = Barcode::query()
            ->where('company_id', $companyId)
            ->where('barcode', $scan)
            ->first();
        if ($barcode instanceof Barcode) {
            $product = Product::query()
                ->where('company_id', $companyId)
                ->find($barcode->product_id);
            if (! $product instanceof Product) {
                throw new RuntimeException('Barkod aynı şirkette bir ürüne çözümlenemedi.');
            }

            return [
                'product_id' => (int) $product->getKey(),
                'code' => (string) $product->code,
                'name' => (string) $product->name,
                'barcode' => (string) $barcode->barcode,
                'matched_by' => 'barcode',
            ];
        }

        $product = Product::query()
            ->where('company_id', $companyId)
            ->where('code', $scan)
            ->first();
        if (! $product instanceof Product) {
            throw ValidationException::withMessages(['scan' => 'Ürün aktif şirkette bulunamadı.']);
        }

        $primaryBarcode = Barcode::query()
            ->where('company_id', $companyId)
            ->where('product_id', $product->getKey())
            ->orderBy('id')
            ->first();

        return [
            'product_id' => (int) $product->getKey(),
            'code' => (string) $product->code,
            'name' => (string) $product->name,
            'barcode' => $primaryBarcode instanceof Barcode ? (string) $primaryBarcode->barcode : null,
            'matched_by' => 'product_code',
        ];
    }

    public function permissionFor(string $operationType): string
    {
        return match ($operationType) {
            'goods_receipt.verify' => 'goods_receipts.view',
            'goods_receipt.finalize' => 'goods_receipts.manage',
            'picking.consume', 'transfer.issue', 'transfer.receive',
            'stock_count.start', 'stock_count.scan', 'stock_count.post' => 'inventory.manage',
            'dispatch.verify' => 'dispatches.view',
            'dispatch.finalize' => 'dispatches.manage',
            'subcontract.send', 'subcontract.receive' => 'subcontract.manage',
            default => throw ValidationException::withMessages(['operation_type' => 'Desteklenmeyen mobil depo işlemi.']),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{replay:bool,data:array<string,mixed>}
     */
    public function execute(
        int $companyId,
        ?int $userId,
        string $clientId,
        string $operationId,
        string $operationType,
        array $payload,
    ): array {
        $clientId = trim($clientId);
        $operationId = trim($operationId);
        $operationType = strtolower(trim($operationType));

        if ($companyId < 1 || $clientId === '' || mb_strlen($clientId) > 80 || ! Str::isUuid($operationId)) {
            throw ValidationException::withMessages(['operation_id' => 'Mobil işlem kimliği geçersiz.']);
        }
        $this->permissionFor($operationType);
        $requestHash = hash('sha256', $this->canonicalJson($payload));

        return DB::transaction(function () use ($companyId, $userId, $clientId, $operationId, $operationType, $payload, $requestHash): array {
            $inserted = DB::table('mobile_client_operations')->insertOrIgnore([
                'company_id' => $companyId,
                'user_id' => $userId,
                'client_id' => $clientId,
                'operation_id' => $operationId,
                'operation_type' => $operationType,
                'request_sha256' => $requestHash,
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $operation = DB::table('mobile_client_operations')
                ->where('company_id', $companyId)
                ->where('client_id', $clientId)
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->first();
            if ($operation === null) {
                throw new RuntimeException('Mobil işlem kaydı oluşturulamadı.');
            }
            if ((string) $operation->operation_type !== $operationType
                || ! hash_equals((string) $operation->request_sha256, $requestHash)) {
                throw new DomainException('Mobil işlem idempotency payload drift algılandı.');
            }

            if ($inserted === 0) {
                if ((string) $operation->status !== 'completed' || $operation->response_payload === null) {
                    throw new DomainException('Aynı mobil işlem halen işleniyor.');
                }
                $decoded = json_decode((string) $operation->response_payload, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($decoded)) {
                    throw new RuntimeException('Mobil işlem replay sonucu geçersiz.');
                }

                return ['replay' => true, 'data' => $decoded];
            }

            $data = $this->perform($companyId, $operationId, $operationType, $payload);
            DB::table('mobile_client_operations')->where('id', $operation->id)->update([
                'status' => 'completed',
                'response_payload' => $this->canonicalJson($data),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return ['replay' => false, 'data' => $data];
        }, 3);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function perform(int $companyId, string $operationId, string $operationType, array $payload): array
    {
        $source = new SourceEffectIdentity($companyId, 'mobile.client_operation', $operationId, $operationType);

        return match ($operationType) {
            'goods_receipt.verify' => $this->verifyGoodsReceipt($companyId, $payload),
            'goods_receipt.finalize' => $this->finalizeGoodsReceipt($payload),
            'picking.consume' => $this->consumePicking($source, $payload),
            'dispatch.verify' => $this->verifyDispatch($companyId, $payload),
            'dispatch.finalize' => $this->finalizeDispatch($payload),
            'transfer.issue' => $this->issueTransfer($source, $payload),
            'transfer.receive' => $this->receiveTransfer($source, $payload),
            'stock_count.start' => $this->startCount($companyId, $operationId, $payload),
            'stock_count.scan' => $this->scanCount($companyId, $payload),
            'stock_count.post' => $this->postCount($companyId, $payload),
            'subcontract.send' => $this->sendSubcontract($companyId, $payload),
            'subcontract.receive' => $this->receiveSubcontract($companyId, $operationId, $payload),
            default => throw new DomainException('Desteklenmeyen mobil depo işlemi.'),
        };
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function verifyGoodsReceipt(int $companyId, array $payload): array
    {
        $receiptId = $this->positiveInt($payload, 'goods_receipt_id');
        $product = $this->lookupProduct($companyId, $this->text($payload, 'scan'));
        $receipt = GoodsReceipt::query()->where('company_id', $companyId)->findOrFail($receiptId);
        $line = GoodsReceiptLine::query()
            ->where('company_id', $companyId)
            ->where('goods_receipt_id', $receipt->getKey())
            ->where('product_id', $product['product_id'])
            ->first();

        return [
            'goods_receipt_id' => (int) $receipt->getKey(),
            'product' => $product,
            'matched' => $line instanceof GoodsReceiptLine,
            'line_id' => $line instanceof GoodsReceiptLine ? (int) $line->getKey() : null,
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function finalizeGoodsReceipt(array $payload): array
    {
        $receipt = $this->finalizeGoodsReceipt->handle($this->positiveInt($payload, 'goods_receipt_id'));

        return ['goods_receipt_id' => (int) $receipt->getKey(), 'status' => $receipt->statusEnum()->value];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function consumePicking(SourceEffectIdentity $source, array $payload): array
    {
        $result = $this->reservations->consume($source, $this->positiveInt($payload, 'reservation_id'));

        return [
            'reservation_id' => (int) $result->reservation->getKey(),
            'status' => $result->reservation->statusEnum()->value,
            'domain_replay' => $result->replayed,
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function verifyDispatch(int $companyId, array $payload): array
    {
        $dispatchId = $this->positiveInt($payload, 'dispatch_id');
        $product = $this->lookupProduct($companyId, $this->text($payload, 'scan'));
        $dispatch = Dispatch::query()->where('company_id', $companyId)->findOrFail($dispatchId);
        $line = DispatchLine::query()
            ->where('company_id', $companyId)
            ->where('dispatch_id', $dispatch->getKey())
            ->where('product_id', $product['product_id'])
            ->first();

        return [
            'dispatch_id' => (int) $dispatch->getKey(),
            'product' => $product,
            'matched' => $line instanceof DispatchLine,
            'line_id' => $line instanceof DispatchLine ? (int) $line->getKey() : null,
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function finalizeDispatch(array $payload): array
    {
        $dispatch = $this->finalizeDispatch->handle($this->positiveInt($payload, 'dispatch_id'));

        return ['dispatch_id' => (int) $dispatch->getKey(), 'status' => $dispatch->statusEnum()->value];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function issueTransfer(SourceEffectIdentity $source, array $payload): array
    {
        $rawLines = $payload['lines'] ?? null;
        if (! is_array($rawLines) || $rawLines === []) {
            throw ValidationException::withMessages(['lines' => 'Transfer satırları zorunludur.']);
        }
        $lines = [];
        foreach ($rawLines as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['lines.'.$index => 'Transfer satırı geçersiz.']);
            }
            $lines[] = new WarehouseTransferIssueLineData(
                $this->positiveInt($row, 'product_id'),
                $this->text($row, 'quantity'),
            );
        }
        $result = $this->transfers->issue(
            $source,
            $this->positiveInt($payload, 'source_warehouse_id'),
            $this->positiveInt($payload, 'source_location_id'),
            $this->positiveInt($payload, 'destination_warehouse_id'),
            $this->positiveInt($payload, 'destination_location_id'),
            $lines,
        );

        return ['transfer_id' => (int) $result->transfer->getKey(), 'domain_replay' => $result->replayed];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function receiveTransfer(SourceEffectIdentity $source, array $payload): array
    {
        $result = $this->transfers->receive(
            $source,
            $this->positiveInt($payload, 'transfer_id'),
            $this->positiveInt($payload, 'line_id'),
            $this->text($payload, 'quantity'),
        );

        return [
            'transfer_id' => (int) $result->transfer->getKey(),
            'receipt_id' => (int) $result->receipt->getKey(),
            'domain_replay' => $result->replayed,
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function startCount(int $companyId, string $operationId, array $payload): array
    {
        $count = $this->stockCounts->start($companyId, $this->positiveInt($payload, 'location_id'), 'mobile:'.$operationId);

        return ['stock_count_id' => (int) $count->getKey(), 'status' => (string) $count->status];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function scanCount(int $companyId, array $payload): array
    {
        $line = $this->stockCounts->scanBarcode(
            $companyId,
            $this->positiveInt($payload, 'stock_count_id'),
            $this->text($payload, 'scan'),
            isset($payload['quantity']) ? (string) $payload['quantity'] : '1',
        );

        return [
            'stock_count_id' => (int) $line->stock_count_id,
            'line_id' => (int) $line->getKey(),
            'product_id' => (int) $line->product_id,
            'counted_quantity' => (string) $line->counted_quantity,
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function postCount(int $companyId, array $payload): array
    {
        $count = $this->stockCounts->post($companyId, $this->positiveInt($payload, 'stock_count_id'));

        return ['stock_count_id' => (int) $count->getKey(), 'status' => (string) $count->status];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function sendSubcontract(int $companyId, array $payload): array
    {
        $order = $this->subcontract->sendMaterials($companyId, $this->positiveInt($payload, 'order_id'));

        return ['order_id' => (int) $order->getKey(), 'status' => (string) $order->status];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function receiveSubcontract(int $companyId, string $operationId, array $payload): array
    {
        $rawConsumption = $payload['consumption'] ?? null;
        if (! is_array($rawConsumption) || $rawConsumption === []) {
            throw ValidationException::withMessages(['consumption' => 'Fason tüketim satırları zorunludur.']);
        }
        $consumption = [];
        foreach ($rawConsumption as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['consumption.'.$index => 'Fason tüketim satırı geçersiz.']);
            }
            $consumption[] = [
                'product_id' => $this->positiveInt($row, 'product_id'),
                'quantity' => $this->text($row, 'quantity'),
            ];
        }
        $receipt = $this->subcontract->receiveOutput(
            $companyId,
            $this->positiveInt($payload, 'order_id'),
            'mobile:'.$operationId,
            $this->text($payload, 'output_quantity'),
            $consumption,
        );

        return ['receipt_id' => (int) $receipt->getKey(), 'order_id' => (int) $receipt->subcontract_order_id];
    }

    /** @param array<string,mixed> $payload */
    private function positiveInt(array $payload, string $key): int
    {
        $value = filter_var($payload[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! is_int($value)) {
            throw ValidationException::withMessages([$key => $key.' pozitif tamsayı olmalıdır.']);
        }

        return $value;
    }

    /** @param array<string,mixed> $payload */
    private function text(array $payload, string $key): string
    {
        $value = trim((string) ($payload[$key] ?? ''));
        if ($value === '') {
            throw ValidationException::withMessages([$key => $key.' zorunludur.']);
        }

        return $value;
    }

    /** @param array<string,mixed> $payload */
    private function canonicalJson(array $payload): string
    {
        return json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
