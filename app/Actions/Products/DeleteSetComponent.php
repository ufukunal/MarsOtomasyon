<?php

namespace App\Actions\Products;

use App\Exceptions\StaleRecordException;
use App\Models\Period\Product;
use App\Models\Period\ProductSet;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;

final class DeleteSetComponent
{
    public function handle(Product $set, ProductSet $line, int $expectedVersion): void
    {
        MutationAuthorizer::authorize('products.update');
        PeriodContext::ensureWritable();

        abort_unless((int) $line->set_product_id === (int) $set->id, 404);

        $deleted = ProductSet::query()
            ->whereKey($line->id)
            ->where('version', $expectedVersion)
            ->delete();

        if ($deleted !== 1) {
            throw new StaleRecordException('Set bileşeni eşzamanlı olarak değiştirildi.');
        }
    }
}
