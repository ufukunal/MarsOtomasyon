<?php

use App\Actions\Products\SaveVariantAttribute;
use App\Actions\Products\SaveVariantGroup;
use App\Actions\Products\SaveVariantValues;
use App\Models\Period\Product;
use Illuminate\Validation\ValidationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('writes normalized variant values and permits updating a previously selected attribute', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $product = Product::query()->findOrFail($ids['product']);
            $group = app(SaveVariantGroup::class)->handle(['name' => 'V4 Color']);
            $attribute = app(SaveVariantAttribute::class)->handle($group, ['name' => 'Main color']);

            $save = app(SaveVariantValues::class);
            expect($save->handle($product, $group->id, [$attribute->id => '  Blue  ']))->toBe([]);
            expect($product->fresh()->variantValues()->firstOrFail()->value)->toBe('Blue');

            expect($save->handle($product->fresh(), $group->id, [$attribute->id => 'Red']))->toBe([]);
            expect($product->fresh()->variantValues()->count())->toBe(1)
                ->and($product->fresh()->variantValues()->firstOrFail()->value)->toBe('Red');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects attaching a variant attribute from another group without changing the product', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $product = Product::query()->findOrFail($ids['product']);
            $first = app(SaveVariantGroup::class)->handle(['name' => 'V4 Size']);
            $second = app(SaveVariantGroup::class)->handle(['name' => 'V4 Weight']);
            $foreign = app(SaveVariantAttribute::class)->handle($second, ['name' => 'Thickness']);

            expect(fn () => app(SaveVariantValues::class)->handle(
                $product, $first->id, [$foreign->id => 'Heavy'],
            ))->toThrow(ValidationException::class);

            expect($product->fresh()->variantValues()->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
