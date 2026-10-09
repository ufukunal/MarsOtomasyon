<?php

use App\Support\Security\SensitiveFieldMasker;

it('v2 national ID masking retains only permitted first three and last two characters', function () {
    expect(SensitiveFieldMasker::nationalId('12345678901'))->toBe('123*****01')
        ->and(SensitiveFieldMasker::nationalId('1234'))->toBe('*****')
        ->and(SensitiveFieldMasker::nationalId(''))->toBe('')
        ->and(SensitiveFieldMasker::nationalId(null))->toBeNull();
});

it('v2 only explicit full-view permission discloses original national ID', function () {
    expect(SensitiveFieldMasker::nationalId('12345678901', true))->toBe('12345678901')
        ->and(SensitiveFieldMasker::nationalId('12345678901', false))->not->toBe('12345678901');
});

it('v2 phone masking strips formatting before calculating last four digits', function () {
    expect(SensitiveFieldMasker::phone('+90 (532) 123 45 67'))->toBe('***4567')
        ->and(SensitiveFieldMasker::phone('abc'))->toBe('****')
        ->and(SensitiveFieldMasker::phone('12'))->toBe('****');
});

it('v2 phone reveal is denied by default and supported only with explicit permission', function () {
    $original = '+90 (532) 123 45 67';
    expect(SensitiveFieldMasker::phone($original))->not->toBe($original)
        ->and(SensitiveFieldMasker::phone($original, true))->toBe($original)
        ->and(SensitiveFieldMasker::phone(null))->toBeNull()
        ->and(SensitiveFieldMasker::phone(''))->toBe('');
});
