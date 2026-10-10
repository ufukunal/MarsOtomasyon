<?php

use App\Support\Search\SearchIndexSchema;

it('rejects invalid index identifiers before any PostgreSQL statement', function (string $bad): void {
    expect(fn () => SearchIndexSchema::dropIndex($bad))->toThrow(InvalidArgumentException::class);
    expect(fn () => SearchIndexSchema::createTrigramIndex($bad))->toThrow(InvalidArgumentException::class);
})->with([
    'stock;DROP TABLE products', 'UPPERCASE', 'table-name', 'table name', '123bad', 'a"b',
]);
