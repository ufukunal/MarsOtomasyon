<?php

namespace App\Actions\Stock;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\DataObjects\StockMovementData;
use App\Models\Period\StockCount;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostStockCount
{
    public function __construct(
        private readonly GenerateDocumentNumber $generateNumber,
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly RecordStockMovement $recordMovement,
    ) {}

    public function handle(int $countId, string $idempotencyKey): StockCount
    {
        MutationAuthorizer::authorize('stock_counts.update');
        $result = IdempotencyKey::run($idempotencyKey, "stock_count.post:{$countId}", fn (): int => $this->post($countId));

        return StockCount::query()->with(['location', 'lines.product'])->findOrFail((int) $result);
    }

    private function post(int $countId): int
    {
        return DB::connection('period')->transaction(function () use ($countId): int {
            $count = StockCount::query()->lockForUpdate()->findOrFail($countId);

            if ($count->status !== 'review') {
                throw new DomainException('Yalnız incelemedeki sayım kesinleştirilebilir.');
            }

            $date = CarbonImmutable::parse((string) $count->count_date);
            $this->ensurePeriodOpen->handle($date);
            $number = $count->number ?: $this->generateNumber->handle('stock_count', $date->year);
            $actor = auth()->user();

            $lines = $count->lines()
                ->where('is_approved', true)
                ->where('difference', '<>', 0)
                ->orderBy('product_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lines as $line) {
                $difference = (string) $line->difference;
                $direction = bccomp($difference, '0', 3) > 0 ? 'in' : 'out';
                $quantity = $direction === 'in' ? $difference : bcmul($difference, '-1', 3);

                $this->recordMovement->handle(new StockMovementData(
                    productId: $line->product_id,
                    locationId: $count->location_id,
                    movementDate: $date->toDateString(),
                    direction: $direction,
                    reason: 'count',
                    quantity: $quantity,
                    updatesAverage: false,
                    documentType: 'stock_count',
                    documentId: $count->id,
                    documentNo: $number,
                    note: $line->note,
                    actorUserId: $actor?->id,
                    actorUserName: $actor?->name,
                ));
            }

            $count->number = $number;
            $count->status = 'posted';
            $count->posted_by = $actor?->id;
            $count->posted_at = now();
            $count->save();

            AuditContext::period(
                'Stok sayımı kesinleştirildi.',
                ['stock_count_id' => $count->id, 'number' => $number, 'approved_difference_lines' => $lines->count()],
                $count,
                'stock_count_posted',
            );

            return $count->id;
        }, attempts: 3);
    }
}
