<?php

namespace App\Actions\Products;

use App\Models\Period\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderProductImages
{
    public function handle(Product $product, string $collection, array $attachmentIds): void
    {
        $ownedIds = $product->attachments()
            ->where('collection', $collection)
            ->whereIn('id', $attachmentIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($ownedIds) !== count(array_unique(array_map('intval', $attachmentIds)))) {
            throw ValidationException::withMessages([
                'images' => 'Sıralama listesinde ürüne/sete ait olmayan görsel var.',
            ]);
        }

        DB::connection('period')->transaction(function () use ($attachmentIds): void {
            foreach (array_values($attachmentIds) as $index => $id) {
                DB::connection('period')->table('attachments')
                    ->where('id', (int) $id)
                    ->update(['sort_order' => $index]);
            }
        });
    }
}
