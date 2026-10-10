<?php

use App\Actions\Catalog\SaveProductCategory;
use App\Actions\Products\SaveVariantAttribute;
use App\Actions\Products\SaveVariantGroup;
use App\Actions\ReferenceData\SaveUnit;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('rejects category depth greater than three and moving a category below itself', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $action = app(SaveProductCategory::class);
            $root = $action->handle(['name' => 'V4 Root']);
            $child = $action->handle(['name' => 'V4 Child', 'parent_id' => $root->id]);
            $grandchild = $action->handle(['name' => 'V4 Grandchild', 'parent_id' => $child->id]);

            expect(fn () => $action->handle(['name' => 'V4 Deep', 'parent_id' => $grandchild->id]))
                ->toThrow(ValidationException::class);
            expect(fn () => $action->handle([
                'name' => 'V4 Root', 'parent_id' => $root->id,
            ], $root))->toThrow(ValidationException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('prevents deleting the last active base unit or editing a persisted unit code', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $action = app(SaveUnit::class);
            $unit = $action->handle([
                'code' => 'V4'.substr(strtoupper(Str::random(6)), 0, 6),
                'name' => 'V4 Unit', 'is_base' => true,
            ]);

            expect(fn () => $action->handle([
                'code' => 'CODE-CHANGED', 'name' => 'V4 Unit', 'is_base' => true,
            ], $unit))->toThrow(LogicException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('creates variant attributes within their parent group and rejects foreign updates', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $groups = app(SaveVariantGroup::class);
            $first = $groups->handle(['name' => 'V4 Size']);
            $second = $groups->handle(['name' => 'V4 Color']);
            $attributes = app(SaveVariantAttribute::class);
            $size = $attributes->handle($first, ['name' => 'Length', 'sort_order' => 0]);

            expect($size->variant_group_id)->toBe($first->id);

            expect(fn () => $attributes->handle(
                $second, ['name' => 'Width'], $size,
            ))->toThrow(HttpException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
