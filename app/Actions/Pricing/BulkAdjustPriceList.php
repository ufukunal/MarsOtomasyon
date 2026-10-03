<?php

namespace App\Actions\Pricing;

use App\Support\Period\PeriodContext;
use App\Support\Auth\MutationAuthorizer;
use App\Models\Period\PriceList;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BulkAdjustPriceList
{
    public function handle(PriceList $list, string $percent): int
    {
        MutationAuthorizer::authorize('price_lists.update');
        PeriodContext::ensureWritable();
        $multiplier = bcadd('1', bcdiv($percent, '100', 8), 8);

        if (bccomp($multiplier, '0', 8) < 0) {
            throw ValidationException::withMessages([
                'percent' => 'Toplu fiyat değişimi fiyatı negatife indiremez.',
            ]);
        }
        $updated = 0;

        DB::connection('period')->transaction(function () use ($list, $multiplier, &$updated): void {
            $list->items()
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function ($item) use ($multiplier, &$updated): void {
                    $item->updateWithVersion([
                        'price' => bcmul((string) $item->price, $multiplier, 4),
                    ], (int) $item->version);

                    $updated++;
                });
        });

        return $updated;
    }
}
