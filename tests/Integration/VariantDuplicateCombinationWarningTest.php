<?php

use App\Actions\Products\SaveVariantAttribute;
use App\Actions\Products\SaveVariantGroup;
use App\Actions\Products\SaveVariantValues;
use App\Models\Period\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('warns when two products share the exact same variant attribute combination', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();
        try {
            $first = IsolatedPostgres::productAndLocation();
            $second = IsolatedPostgres::productAndLocation();
            $group = app(SaveVariantGroup::class)->handle(['name' => 'V4 size']);
            $attribute = app(SaveVariantAttribute::class)->handle($group, ['name' => 'Size']);
            $action = app(SaveVariantValues::class);
            $one = Product::query()->findOrFail($first['product']);
            $two = Product::query()->findOrFail($second['product']);

            expect($action->handle($one, $group->id, [$attribute->id => 'M']))->toBe([]);
            $warnings = $action->handle($two, $group->id, [$attribute->id => 'M']);
            expect($warnings)->toHaveCount(1)
                ->and($warnings[0])->toContain($one->code);

            expect($action->handle($two->fresh(), $group->id, [$attribute->id => 'L']))->toBe([])
                ->and(DB::connection('period')->table('product_variant_values')
                    ->where('product_id', $two->id)->count())->toBe(1);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects an attribute from a different variant group without rewriting existing values', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();
        try {
            $ids = IsolatedPostgres::productAndLocation();
            $a = app(SaveVariantGroup::class)->handle(['name' => 'V4 First']);
            $b = app(SaveVariantGroup::class)->handle(['name' => 'V4 Second']);
            $attribute = app(SaveVariantAttribute::class)->handle($a, ['name' => 'Primary']);
            $product = Product::query()->findOrFail($ids['product']);

            expect(fn () => app(SaveVariantValues::class)->handle(
                $product, $b->id, [$attribute->id => 'unowned'],
            ))->toThrow(ValidationException::class);

            expect(DB::connection('period')->table('product_variant_values')
                ->where('product_id', $product->id)->count())->toBe(0);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
