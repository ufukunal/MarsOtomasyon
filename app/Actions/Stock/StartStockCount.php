<?php

namespace App\Actions\Stock;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\StockBalance;
use App\Models\Period\StockCount;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class StartStockCount
{
    public function __construct(private readonly EnsurePeriodOpen $ensurePeriodOpen) {}

    public function handle(int $countId, string $idempotencyKey): StockCount
    {
        MutationAuthorizer::authorize('stock_counts.update');
        $result = IdempotencyKey::run($idempotencyKey, "stock_count.start:{$countId}", fn (): int => $this->start($countId));
        return StockCount::query()->with(['location','lines.product'])->findOrFail((int) $result);
    }

    private function start(int $countId): int
    {
        return DB::connection('period')->transaction(function () use ($countId): int {
            $count = StockCount::query()->lockForUpdate()->findOrFail($countId);
            if ($count->status !== 'draft') {
                throw new DomainException('Yalnız taslak sayım başlatılabilir.');
            }

            $date = CarbonImmutable::parse((string) $count->count_date);
            $this->ensurePeriodOpen->handle($date);
            $lines = $count->lines()->orderBy('product_id')->orderBy('id')->lockForUpdate()->get();

            if ($lines->isEmpty()) {
                throw new DomainException('Sayım satırı bulunamadı.');
            }

            foreach ($lines as $line) {
                $quantity = StockBalance::query()
                    ->where('product_id', $line->product_id)
                    ->where('location_id', $count->location_id)
                    ->value('quantity');

                $line->setAttribute('system_quantity', bcadd((string) ($quantity ?? '0'), '0', 3));
                $line->setAttribute('counted_quantity', null);
                $line->setAttribute('difference', '0.000');
                $line->is_approved = false;
                $line->save();
            }

            $count->status = 'counting';
            $count->save();

            AuditContext::period(
                'Stok sayımı başlatıldı; sistem miktarları donduruldu.',
                ['stock_count_id'=>$count->id,'location_id'=>$count->location_id,'count_date'=>$date->toDateString()],
                $count,
                'stock_count_started',
            );

            return $count->id;
        }, attempts: 3);
    }
}
