<?php

use App\Support\Import\ImportFileReader;
use Illuminate\Support\Facades\Storage;

it('streams semicolon-separated CSV cards without losing an explicit zero price', function (): void {
    $disk = 'mars_v4_import_csv';
    Storage::fake($disk);
    Storage::disk($disk)->put('cards.csv', "code;name;price\nSKU-1;First;0\nSKU-2;Second;15.50\n");

    $reader = new ImportFileReader;
    $rows = $reader->rows($disk, 'cards.csv', 'cards.csv');

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['code' => 'SKU-1', 'name' => 'First', 'price' => '0'])
        ->and($reader->previewRows($disk, 'cards.csv', 'cards.csv', 1))->toHaveCount(1);
});

it('streams BOM-prefixed JSON arrays containing escaped quotes and nested values', function (): void {
    $disk = 'mars_v4_import_json';
    Storage::fake($disk);
    $records = [
        ['sku' => 'SKU-1', 'name' => 'Widget, "alpha"', 'meta' => ['colors' => ['red', 'blue']]],
        ['sku' => 'SKU-2', 'name' => 'Büyük Ürün', 'amount' => 0],
    ];
    $json = json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    Storage::disk($disk)->put('rows.json', "\xEF\xBB\xBF".$json);

    $reader = new ImportFileReader;

    expect($reader->rows($disk, 'rows.json', 'rows.json'))->toBe($records)
        ->and($reader->previewRows($disk, 'rows.json', 'rows.json', 0))->toBe([$records[0]]);
});

it('rejects incomplete or non-array JSON import roots before accepting rows', function (string $content): void {
    $disk = 'mars_v4_import_invalid';
    Storage::fake($disk);
    Storage::disk($disk)->put('invalid.json', $content);

    expect(fn () => (new ImportFileReader)->rows($disk, 'invalid.json', 'invalid.json'))
        ->toThrow(RuntimeException::class);
})->with(['{"sku":"P-1"}', '[{"sku": 1}', '[] trailing']);

it('refuses unsupported card import extensions', function (): void {
    $disk = 'mars_v4_import_type';
    Storage::fake($disk);
    Storage::disk($disk)->put('bad.php', '<?php echo "bad";');

    expect(fn () => (new ImportFileReader)->rows($disk, 'bad.php', 'bad.php'))
        ->toThrow(RuntimeException::class);
});
