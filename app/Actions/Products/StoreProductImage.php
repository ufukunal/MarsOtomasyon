<?php

namespace App\Actions\Products;

use App\Support\Auth\MutationAuthorizer;
use App\Actions\Attachments\StoreAttachment;
use App\Models\Attachment;
use App\Models\Period\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class StoreProductImage
{
    public function handle(Product $product, UploadedFile $file, string $collection): Attachment
    {
        MutationAuthorizer::authorize('products.update');
        if (! in_array($collection, config('product_images.collections', []), true)) {
            throw ValidationException::withMessages([
                'collection' => 'Geçersiz görsel seti.',
            ]);
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, config('product_images.mimes', []), true)) {
            throw ValidationException::withMessages([
                'image' => 'Ürün görsellerinde yalnız JPG/JPEG, PNG ve WEBP kabul edilir.',
            ]);
        }

        $sortOrder = (int) ($product->attachments()
            ->where('collection', $collection)
            ->max('sort_order') ?? -1) + 1;

        return app(StoreAttachment::class)->handle(
            $product,
            $file,
            $collection,
            $sortOrder,
        );
    }
}
