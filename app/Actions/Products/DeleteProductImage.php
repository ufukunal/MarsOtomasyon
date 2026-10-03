<?php

namespace App\Actions\Products;

use App\Models\Attachment;
use App\Models\Period\Product;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;

final class DeleteProductImage
{
    public function handle(Product $product, Attachment $attachment): void
    {
        MutationAuthorizer::authorize('products.update');
        PeriodContext::ensureWritable();

        abort_unless(
            $product->attachments()->whereKey($attachment->id)->exists(),
            404,
        );

        $attachment->delete();
    }
}
