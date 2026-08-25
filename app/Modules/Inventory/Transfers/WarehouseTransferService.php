<?php

namespace App\Modules\Inventory\Transfers;

use App\Foundation\Clock\Clock;
use App\Foundation\Idempotency\IdempotencyStatus;
use App\Foundation\Idempotency\IdempotencyStore;
use App\Foundation\Idempotency\RequestFingerprint;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Ledger\PostStockMovementData;
use App\Modules\Inventory\Ledger\StockMovementPoster;
use App\Modules\Inventory\Models\WarehouseTransfer;
use App\Modules\Inventory\Models\WarehouseTransferLine;
use App\Modules\Inventory\Models\WarehouseTransferReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class WarehouseTransferService
{
    private const ISSUE_SCOPE = 'inventory.warehouse_transfer.issue';

    private const RECEIPT_SCOPE = 'inventory.warehouse_transfer.receipt';

    public function __construct(
        private IdempotencyStore $idempotency,
        private StockMovementPoster $stockMovementPoster,
        private Clock $clock,
    ) {}

    /** @param list<WarehouseTransferIssueLineData> $lines */
    public function issue(
        SourceEffectIdentity $sourceEffect,
        int $sourceWarehouseId,
        int $sourceLocationId,
        int $destinationWarehouseId,
        int $destinationLocationId,
        array $lines,
    ): WarehouseTransferIssueResult {
        $this->assertInsideTransaction();

        if ($sourceWarehouseId === $destinationWarehouseId && $sourceLocationId === $destinationLocationId) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'Depo transferinin kaynak ve hedef stok scope alanları aynı olamaz.',
            ]);
        }
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Depo transferinde en az bir stok satırı bulunmalıdır.',
            ]);
        }

        $normalizedLines = [];
        foreach ($lines as $index => $line) {
            if (! $line instanceof WarehouseTransferIssueLineData) {
                throw new LogicException('Warehouse transfer issue lines must use WarehouseTransferIssueLineData.');
            }

            $normalizedLines[] = [
                'line_number' => $index + 1,
                'product_id' => $line->productId,
                'quantity' => $this->positiveDecimal($line->quantity, 'lines.'.($index + 1).'.quantity'),
            ];
        }

        $fingerprint = RequestFingerprint::fromPayload([
            'company_id' => $sourceEffect->companyId,
            'source_warehouse_id' => $sourceWarehouseId,
            'source_location_id' => $sourceLocationId,
            'destination_warehouse_id' => $destinationWarehouseId,
            'destination_location_id' => $destinationLocationId,
            'lines' => $normalizedLines,
        ]);
        $claim = $this->idempotency->claim(self::ISSUE_SCOPE, $sourceEffect->fingerprint(), $fingerprint);

        if ($claim->isReplay()) {
            $this->assertCompletedReplay($claim->status);

            $existing = WarehouseTransfer::query()
                ->where('company_id', $sourceEffect->companyId)
                ->where('issue_source_type', $sourceEffect->sourceType)
                ->where('issue_source_id', $sourceEffect->sourceId)
                ->where('issue_effect_type', $sourceEffect->effectType)
                ->first();

            if (! $existing instanceof WarehouseTransfer) {
                throw new LogicException('Tamamlanmış transfer issue idempotency kaydının transfer belgesi bulunamadı.');
            }

            return new WarehouseTransferIssueResult($existing, true);
        }

        $now = $this->clock->now();
        $transfer = WarehouseTransfer::query()->create([
            'company_id' => $sourceEffect->companyId,
            'source_warehouse_id' => $sourceWarehouseId,
            'source_location_id' => $sourceLocationId,
            'destination_warehouse_id' => $destinationWarehouseId,
            'destination_location_id' => $destinationLocationId,
            'status' => 'in_transit',
            'issue_source_type' => $sourceEffect->sourceType,
            'issue_source_id' => $sourceEffect->sourceId,
            'issue_effect_type' => $sourceEffect->effectType,
            'issued_at' => $now,
        ]);

        foreach ($normalizedLines as $line) {
            $lineNumber = (int) $line['line_number'];
            $movementIdentity = new SourceEffectIdentity(
                companyId: $sourceEffect->companyId,
                sourceType: 'inventory.warehouse_transfer',
                sourceId: 'transfer-'.$transfer->getKey().'-line-'.$lineNumber,
                effectType: 'inventory.transfer_out',
            );
            $posting = $this->stockMovementPoster->post(new PostStockMovementData(
                sourceEffect: $movementIdentity,
                productId: (int) $line['product_id'],
                warehouseId: $sourceWarehouseId,
                locationId: $sourceLocationId,
                movementType: StockMovementType::TransferOut,
                quantity: (string) $line['quantity'],
                note: 'Warehouse transfer #'.$transfer->getKey().' line '.$lineNumber,
            ));

            WarehouseTransferLine::query()->create([
                'company_id' => $sourceEffect->companyId,
                'transfer_id' => $transfer->getKey(),
                'line_number' => $lineNumber,
                'product_id' => $line['product_id'],
                'issued_quantity' => $line['quantity'],
                'unit_cost' => $posting->movement->unit_cost,
                'issued_value' => $this->absolute((string) $posting->movement->value_delta),
                'received_quantity' => '0.000000',
                'received_value' => '0.000000',
                'issue_movement_id' => $posting->movement->getKey(),
            ]);
        }

        $this->idempotency->complete($claim);

        return new WarehouseTransferIssueResult($transfer, false);
    }

    public function receive(
        SourceEffectIdentity $sourceEffect,
        int $transferId,
        int $lineId,
        string $quantity,
    ): WarehouseTransferReceiptResult {
        $this->assertInsideTransaction();
        $quantity = $this->positiveDecimal($quantity, 'quantity');

        $fingerprint = RequestFingerprint::fromPayload([
            'company_id' => $sourceEffect->companyId,
            'transfer_id' => $transferId,
            'line_id' => $lineId,
            'quantity' => $quantity,
        ]);
        $claim = $this->idempotency->claim(self::RECEIPT_SCOPE, $sourceEffect->fingerprint(), $fingerprint);

        if ($claim->isReplay()) {
            $this->assertCompletedReplay($claim->status);

            $receipt = WarehouseTransferReceipt::query()
                ->where('company_id', $sourceEffect->companyId)
                ->where('source_type', $sourceEffect->sourceType)
                ->where('source_id', $sourceEffect->sourceId)
                ->where('effect_type', $sourceEffect->effectType)
                ->first();
            $transfer = WarehouseTransfer::query()
                ->where('company_id', $sourceEffect->companyId)
                ->find($transferId);

            if (! $receipt instanceof WarehouseTransferReceipt || ! $transfer instanceof WarehouseTransfer) {
                throw new LogicException('Tamamlanmış transfer receipt idempotency kaydı ile custody kayıtları uyuşmuyor.');
            }

            return new WarehouseTransferReceiptResult($transfer, $receipt, true);
        }

        $transfer = WarehouseTransfer::query()
            ->where('company_id', $sourceEffect->companyId)
            ->whereKey($transferId)
            ->lockForUpdate()
            ->firstOrFail();
        if ($transfer->status === 'received') {
            throw ValidationException::withMessages([
                'transfer' => 'Tamamlanmış depo transferine yeni kabul eklenemez.',
            ]);
        }

        $line = WarehouseTransferLine::query()
            ->where('company_id', $sourceEffect->companyId)
            ->where('transfer_id', $transferId)
            ->whereKey($lineId)
            ->lockForUpdate()
            ->firstOrFail();

        $remainingQuantity = $this->subtract((string) $line->issued_quantity, (string) $line->received_quantity);
        if (! $this->lessThanOrEqual($quantity, $remainingQuantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'Transfer kabul miktarı transit kalan miktarı aşamaz.',
            ]);
        }

        $carryingValue = $this->equals($quantity, $remainingQuantity)
            ? $this->subtract((string) $line->issued_value, (string) $line->received_value)
            : $this->multiply($quantity, (string) $line->unit_cost);

        $posting = $this->stockMovementPoster->post(new PostStockMovementData(
            sourceEffect: $sourceEffect,
            productId: (int) $line->product_id,
            warehouseId: (int) $transfer->destination_warehouse_id,
            locationId: (int) $transfer->destination_location_id,
            movementType: StockMovementType::TransferIn,
            quantity: $quantity,
            unitCost: (string) $line->unit_cost,
            note: 'Warehouse transfer #'.$transfer->getKey().' receipt line '.$line->line_number,
            carryingValue: $carryingValue,
        ));

        $now = $this->clock->now();
        $receipt = WarehouseTransferReceipt::query()->create([
            'company_id' => $sourceEffect->companyId,
            'transfer_id' => $transfer->getKey(),
            'line_id' => $line->getKey(),
            'source_type' => $sourceEffect->sourceType,
            'source_id' => $sourceEffect->sourceId,
            'effect_type' => $sourceEffect->effectType,
            'quantity' => $quantity,
            'carrying_value' => $carryingValue,
            'receipt_movement_id' => $posting->movement->getKey(),
            'received_at' => $now,
            'created_at' => $now,
        ]);

        $transfer->refresh();
        $this->idempotency->complete($claim);

        return new WarehouseTransferReceiptResult($transfer, $receipt, false);
    }

    private function positiveDecimal(string $value, string $field): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages([
                $field => 'Transfer miktarı sıfırdan büyük ve en fazla 6 ondalıklı olmalıdır.',
            ]);
        }

        $integerPart = explode('.', $value, 2)[0];
        if (strlen(ltrim($integerPart, '0')) > 14) {
            throw ValidationException::withMessages([
                $field => 'Transfer miktarı desteklenen aralığı aşıyor.',
            ]);
        }

        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) > 0 AS valid',
            [$value, $value],
        );
        if ($row === null || $row->valid !== true) {
            throw ValidationException::withMessages([$field => 'Transfer miktarı sıfırdan büyük olmalıdır.']);
        }

        return (string) $row->value;
    }

    private function multiply(string $left, string $right): string
    {
        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) * CAST(? AS numeric) AS numeric(20,6))::text AS value',
            [$left, $right],
        );

        return $row === null
            ? throw new LogicException('Transfer numeric multiplication did not return a value.')
            : (string) $row->value;
    }

    private function subtract(string $left, string $right): string
    {
        $row = DB::selectOne(
            'SELECT CAST(CAST(? AS numeric) - CAST(? AS numeric) AS numeric(20,6))::text AS value',
            [$left, $right],
        );

        return $row === null
            ? throw new LogicException('Transfer numeric subtraction did not return a value.')
            : (string) $row->value;
    }

    private function absolute(string $value): string
    {
        $row = DB::selectOne('SELECT abs(CAST(? AS numeric))::text AS value', [$value]);

        return $row === null
            ? throw new LogicException('Transfer numeric absolute did not return a value.')
            : (string) $row->value;
    }

    private function lessThanOrEqual(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) <= CAST(? AS numeric) AS valid', [$left, $right]);

        return $row !== null && $row->valid === true;
    }

    private function equals(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) = CAST(? AS numeric) AS valid', [$left, $right]);

        return $row !== null && $row->valid === true;
    }

    private function assertCompletedReplay(IdempotencyStatus $status): void
    {
        if ($status !== IdempotencyStatus::Completed) {
            throw new LogicException('Depo transferi idempotency kaydı tamamlanmamış durumda bırakılamaz.');
        }
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Depo transferi aynı business transaction içinde çalışmalıdır.');
        }
    }
}
