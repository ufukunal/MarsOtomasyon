<?php

namespace App\Actions\Stock;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\DataObjects\StockMovementData;
use App\Exceptions\CostDeviationConfirmationRequiredException;
use App\Models\Period\WarehouseSlip;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostWarehouseSlip
{
    public function __construct(
        private readonly GenerateDocumentNumber $generateNumber,
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly RecordStockMovement $recordMovement,
        private readonly CheckPurchaseCostDeviation $checkCostDeviation,
    ) {}

    public function handle(
        int $slipId,
        string $idempotencyKey,
        bool $acceptCostDeviation = false,
    ): WarehouseSlip {
        MutationAuthorizer::authorize('warehouse_slips.update');

        $result = IdempotencyKey::run(
            $idempotencyKey,
            "warehouse_slip.post:{$slipId}",
            fn (): int => $this->post($slipId, $acceptCostDeviation),
        );

        return WarehouseSlip::query()->with(['location', 'lines.product'])->findOrFail((int) $result);
    }

    private function post(int $slipId, bool $acceptCostDeviation): int
    {
        return DB::connection('period')->transaction(function () use ($slipId, $acceptCostDeviation): int {
            $slip = WarehouseSlip::query()->lockForUpdate()->findOrFail($slipId);

            if ($slip->status !== 'draft') {
                throw new DomainException('Yalnız taslak ambar fişi kesinleştirilebilir.');
            }

            $date = CarbonImmutable::parse((string) $slip->slip_date);
            $this->ensurePeriodOpen->handle($date);

            $lines = $slip->lines()
                ->with('product')
                ->orderBy('product_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw new DomainException('Ambar fişi satırı bulunamadı.');
            }

            $warnings = [];

            if ($slip->direction === 'in') {
                foreach ($lines as $line) {
                    if ($line->unit_cost === null) {
                        continue;
                    }

                    $warning = $this->checkCostDeviation->handle(
                        $line->product_id,
                        (string) $line->unit_cost,
                        false,
                    );

                    if ($warning) {
                        $warnings[] = $warning->message;
                    }
                }
            }

            if ($warnings !== [] && ! $acceptCostDeviation) {
                throw new CostDeviationConfirmationRequiredException($warnings);
            }

            $number = $slip->number ?: $this->generateNumber->handle('warehouse_slip', $date->year);
            $actor = auth()->user();

            foreach ($lines as $line) {
                if ($slip->direction === 'in' && $line->unit_cost !== null && $acceptCostDeviation) {
                    $this->checkCostDeviation->handle(
                        $line->product_id,
                        (string) $line->unit_cost,
                        true,
                    );
                }

                $this->recordMovement->handle(new StockMovementData(
                    productId: $line->product_id,
                    locationId: $slip->location_id,
                    movementDate: $date->toDateString(),
                    direction: $slip->direction,
                    reason: 'adjustment',
                    quantity: (string) $line->quantity,
                    unitCost: $slip->direction === 'in' && $line->unit_cost !== null
                        ? (string) $line->unit_cost
                        : null,
                    updatesAverage: false,
                    documentType: 'warehouse_slip',
                    documentId: $slip->id,
                    documentNo: $number,
                    note: $line->note,
                    actorUserId: $actor?->id,
                    actorUserName: $actor?->name,
                ));
            }

            $slip->number = $number;
            $slip->status = 'posted';
            $slip->posted_by = $actor?->id;
            $slip->posted_at = now();
            $slip->save();

            AuditContext::period(
                'Ambar fişi kesinleştirildi.',
                [
                    'warehouse_slip_id' => $slip->id,
                    'number' => $number,
                    'direction' => $slip->direction,
                    'reason' => $slip->reason,
                ],
                $slip,
                'warehouse_slip_posted',
            );

            return $slip->id;
        }, attempts: 3);
    }
}
