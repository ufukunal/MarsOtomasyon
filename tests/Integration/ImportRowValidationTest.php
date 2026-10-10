<?php

use App\Actions\Import\ContactRowImporter;
use App\Actions\Import\OpeningStockRowImporter;
use App\Actions\Import\PriceListRowImporter;
use App\Actions\Import\ProductRowImporter;
use Tests\Support\IsolatedPostgres;

it('rejects malformed contacts and products before opening an import write transaction', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $contact = (new ContactRowImporter)->validate(['title' => '  ', 'email' => 'bad-email']);
        expect($contact->valid)->toBeFalse()
            ->and($contact->errors)->toHaveKeys(['title', 'email']);

        $product = (new ProductRowImporter)->validate([
            'code' => '', 'name' => '', 'unit_code' => 'MISSING-V4',
        ]);
        expect($product->valid)->toBeFalse()
            ->and($product->errors)->toHaveKeys(['code', 'name', 'unit_code']);
    });
});

it('rejects price-list imports with unresolved references and nonnumeric prices', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $result = (new PriceListRowImporter)->validate([
            'list_name' => 'UNKNOWN-V4',
            'product_code' => 'MISSING-V4',
            'price' => 'not-a-number',
        ]);
        expect($result->valid)->toBeFalse()
            ->and($result->errors)->toHaveKeys(['list_name', 'product_code', 'price']);
    });
});

it('requires positive opening stock and existing locations and products', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $check = (new OpeningStockRowImporter)->validate([
            'product_code' => 'UNKNOWN-V4', 'location_code' => 'UNKNOWN-V4',
            'quantity' => '0', 'unit_cost' => '-1',
        ]);
        expect($check->valid)->toBeFalse()
            ->and($check->errors)->toHaveKeys(['product_code', 'location_code', 'quantity', 'unit_cost']);
        expect(fn () => (new OpeningStockRowImporter)->import([]))
            ->toThrow(LogicException::class);
    });
});
