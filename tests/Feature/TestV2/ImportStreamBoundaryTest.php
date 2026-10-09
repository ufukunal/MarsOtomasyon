<?php

use App\Support\Import\ImportFileReader;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('v2-import-isolated');
});

it('v2 streamed JSON array with BOM returns typed records without full JSON root wrapping', function () {
    Storage::disk('v2-import-isolated')->put('cards.json', "\xEF\xBB\xBF [\n {\"code\":\"ITEM-1\",\"name\":\"Ürün\"},\n {\"code\":\"ITEM-2\",\"stock\":3}\n]");

    $reader = app(ImportFileReader::class);
    expect($reader->rows('v2-import-isolated', 'cards.json', 'cards.json'))
        ->toBe([
            ['code' => 'ITEM-1', 'name' => 'Ürün'],
            ['code' => 'ITEM-2', 'stock' => 3],
        ]);
});

it('v2 import preview stops at the requested row limit', function () {
    Storage::disk('v2-import-isolated')->put('cards.csv', "code;name\nI1;First\nI2;Second\nI3;Third\n");

    expect(app(ImportFileReader::class)->previewRows('v2-import-isolated', 'cards.csv', 'cards.csv', 2))
        ->toBe([['code' => 'I1', 'name' => 'First'], ['code' => 'I2', 'name' => 'Second']]);
});

it('v2 import JSON refuses non-array root and incomplete documents', function () {
    Storage::disk('v2-import-isolated')->put('bad.json', '{"code":"ITEM-1"}');
    Storage::disk('v2-import-isolated')->put('truncated.json', '[{"code":"ITEM-1"}');

    foreach (['bad.json', 'truncated.json'] as $path) {
        expect(fn () => app(ImportFileReader::class)->rows('v2-import-isolated', $path, $path))
            ->toThrow(RuntimeException::class);
    }
});

it('v2 import rejects unknown filename extensions before parsing', function () {
    Storage::disk('v2-import-isolated')->put('cards.exe', 'binary');

    expect(fn () => app(ImportFileReader::class)->rows('v2-import-isolated', 'cards.exe', 'cards.exe'))
        ->toThrow(RuntimeException::class, 'Desteklenmeyen içe aktarma dosya türü.');
});

it('v2 CSV import must normalize UTF-8 BOM before matching the first header', function () {
    // Expected behavior: without BOM removal first mapped field becomes a different key.
    // Existing ImportFileReader::csv() does not visibly strip BOM: regression may FAIL.
    Storage::disk('v2-import-isolated')->put('cards.csv', "\xEF\xBB\xBFcode;name\nP-1;Ankara Ürün\n");

    expect(app(ImportFileReader::class)->rows('v2-import-isolated', 'cards.csv', 'cards.csv'))
        ->toBe([['code' => 'P-1', 'name' => 'Ankara Ürün']]);
});
