<?php

use App\Support\Search\SearchNormalizer;

it('normalizes Turkish diacritics and punctuation for search', function (?string $input, string $expected): void {
    expect(SearchNormalizer::make($input))->toBe($expected);
})->with([
    ['İSTANBUL ŞİŞLİ', 'istanbul sisli'],
    ['Çağrı Öztürk', 'cagri ozturk'],
    ['Ürün-001 / Büyük', 'urun 001 buyuk'],
    ['  SİPARİŞ  100  ', 'siparis 100'],
    ['Âlî', 'ali'],
    [null, ''],
    ['', ''],
]);
