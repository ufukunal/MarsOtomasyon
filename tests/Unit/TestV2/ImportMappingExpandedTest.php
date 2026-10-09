<?php

use App\Support\Import\ImportMapping;

it('v2 import mapping exposes mandatory product identity columns', function () {
    $fields = ImportMapping::fields('product');
    expect($fields['code']['required'])->toBeTrue()
        ->and($fields['name']['required'])->toBeTrue()
        ->and($fields['unit_code']['required'])->toBeTrue()
        ->and($fields['barcode']['required'])->toBeFalse();
});

it('v2 import mapping exposes mandatory opening stock amount and cost', function () {
    $fields = ImportMapping::fields('opening_stock');
    expect($fields['product_code']['required'])->toBeTrue()
        ->and($fields['location_code']['required'])->toBeTrue()
        ->and($fields['quantity']['required'])->toBeTrue()
        ->and($fields['unit_cost']['required'])->toBeTrue();
});

it('v2 import mapping correctly maps explicit source columns including Turkish data', function () {
    $row = ['Malzeme Kodu' => 'P-123', 'Açıklama' => 'Çeşitli ürün', 'Birim' => 'ADET'];
    expect(ImportMapping::map($row, [
        'code' => 'Malzeme Kodu', 'name' => 'Açıklama', 'unit_code' => 'Birim',
    ]))->toBe(['code' => 'P-123', 'name' => 'Çeşitli ürün', 'unit_code' => 'ADET']);
});

it('v2 import mapping does not invent values for missing or unselected columns', function () {
    expect(ImportMapping::map(['value' => 'VISIBLE'], [
        'name' => 'missing', 'code' => null, 'email' => '',
    ]))->toBe(['name' => null, 'code' => null, 'email' => null]);
});

it('v2 unknown import entity type yields no phantom required columns', function () {
    expect(ImportMapping::fields('nonexistent_entity'))->toBe([]);
});