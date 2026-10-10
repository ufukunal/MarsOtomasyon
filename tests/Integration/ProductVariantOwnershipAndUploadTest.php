<?php

use App\Actions\Products\SaveVariantValues;
use App\Actions\Products\StoreProductImage;
use App\Models\Period\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('rejects variant attribute IDs that do not belong to the selected variant group', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $fixture = IsolatedPostgres::productAndLocation();
            $product = Product::query()->findOrFail($fixture['product']);

            expect(fn () => app(SaveVariantValues::class)->handle(
                $product, 999999, [999998 => 'Blue'],
            ))->toThrow(ValidationException::class);

            expect(DB::connection('period')->table('product_variant_values')
                ->where('product_id', $product->id)->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects unrecognized product image collections and PHP uploads before file persistence', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $fixture = IsolatedPostgres::productAndLocation();
            $product = Product::query()->findOrFail($fixture['product']);
            $file = UploadedFile::fake()->createWithContent('invoice.php', '<?php echo 1;');

            expect(fn () => app(StoreProductImage::class)->handle($product, $file, 'unknown_collection'))
                ->toThrow(ValidationException::class);

            $validCollection = config('product_images.collections.0');
            expect($validCollection)->toBeString();

            expect(fn () => app(StoreProductImage::class)->handle($product, $file, $validCollection))
                ->toThrow(ValidationException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
