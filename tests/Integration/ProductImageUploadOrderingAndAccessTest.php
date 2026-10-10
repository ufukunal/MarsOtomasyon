<?php

use App\Actions\Products\DeleteProductImage;
use App\Actions\Products\ReorderProductImages;
use App\Actions\Products\StoreProductImage;
use App\Models\Period\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('stores disposable PNG product images and preserves per-collection ordering', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            Storage::fake('attachments');
            $fixture = IsolatedPostgres::productAndLocation();
            $product = Product::query()->findOrFail($fixture['product']);
            $upload = app(StoreProductImage::class);

            $first = $upload->handle($product, UploadedFile::fake()->image('one.png'), 'Ortak');
            $second = $upload->handle($product, UploadedFile::fake()->image('two.png'), 'Ortak');
            $third = $upload->handle($product, UploadedFile::fake()->image('channel.png'), 'Trendyol');

            expect((int) $first->sort_order)->toBe(0)
                ->and((int) $second->sort_order)->toBe(1)
                ->and((int) $third->sort_order)->toBe(0)
                ->and($first->mime)->toBe('image/png')
                ->and(Storage::disk('attachments')->exists($first->path))->toBeTrue();

            app(ReorderProductImages::class)->handle($product, 'Ortak', [$second->id, $first->id]);

            expect((int) $first->refresh()->sort_order)->toBe(1)
                ->and((int) $second->refresh()->sort_order)->toBe(0)
                ->and((int) $third->refresh()->sort_order)->toBe(0);

            expect(DB::connection('period')->table('attachments')
                ->where('attachable_id', $product->id)->count())->toBe(3);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('prevents deleting another product image even if the attachment ID is known', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            Storage::fake('attachments');
            $owner = IsolatedPostgres::productAndLocation();
            $other = IsolatedPostgres::productAndLocation();
            $ownerProduct = Product::query()->findOrFail($owner['product']);
            $foreignProduct = Product::query()->findOrFail($other['product']);
            $picture = app(StoreProductImage::class)->handle(
                $ownerProduct, UploadedFile::fake()->image('owner.png'), 'Ortak',
            );

            expect(fn () => app(DeleteProductImage::class)->handle($foreignProduct, $picture))
                ->toThrow(HttpException::class);

            expect($ownerProduct->attachments()->whereKey($picture->id)->exists())->toBeTrue()
                ->and(Storage::disk('attachments')->exists($picture->path))->toBeTrue();
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
