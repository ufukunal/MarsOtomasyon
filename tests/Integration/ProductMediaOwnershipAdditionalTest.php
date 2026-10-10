<?php

use App\Actions\Products\DeleteSetComponent;
use App\Actions\Products\ReorderProductImages;
use App\Models\Period\Product;
use App\Models\Period\ProductSet;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('prevents deleting another set product component by substituting the set identifier', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $set = new Product;
            $set->id = 100;
            $foreign = new ProductSet;
            $foreign->id = 200;
            $foreign->set_product_id = 101;

            expect(fn () => app(DeleteSetComponent::class)->handle($set, $foreign, 1))
                ->toThrow(HttpException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects image sort lists containing foreign or nonexistent product attachments', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $ids = IsolatedPostgres::productAndLocation();
            $product = Product::query()->findOrFail($ids['product']);

            expect(fn () => app(ReorderProductImages::class)->handle(
                $product, 'images', [100001, 100002],
            ))->toThrow(ValidationException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
