<?php

namespace App\Actions\Stock;

use App\Models\Period\StockCount;
use App\Support\Auth\MutationAuthorizer;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReviewStockCount
{
    public function handle(int $countId): StockCount
    {
        MutationAuthorizer::authorize('stock_counts.update');

        return DB::connection('period')->transaction(function () use ($countId): StockCount {
            $count = StockCount::query()->lockForUpdate()->findOrFail($countId);
            if ($count->status !== 'counting') {
                throw new DomainException('Yalnız devam eden sayım incelemeye alınabilir.');
            }
            $count->status = 'review';
            $count->save();
            return $count->refresh()->load(['location','lines.product']);
        }, attempts: 3);
    }
}
