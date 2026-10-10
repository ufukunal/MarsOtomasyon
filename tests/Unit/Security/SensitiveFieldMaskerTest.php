<?php

use App\Support\Security\SensitiveFieldMasker as Mask;

it('hides national id except explicit authorized full view', function (): void {
    expect(Mask::nationalId('12345678901'))->toBe('123*****01')
        ->and(Mask::nationalId('1234'))->toBe('*****')
        ->and(Mask::nationalId('12345678901', true))->toBe('12345678901')
        ->and(Mask::nationalId(null))->toBeNull()
        ->and(Mask::nationalId(''))->toBe('');
});

it('keeps only last four phone digits by default', function (): void {
    expect(Mask::phone('+90 (532) 555 12 34'))->toBe('***1234')
        ->and(Mask::phone('12'))->toBe('****')
        ->and(Mask::phone('5325551234', true))->toBe('5325551234')
        ->and(Mask::phone(null))->toBeNull();
});
