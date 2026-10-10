<?php

use App\Actions\Companies\InspectCopiedRecords;
use App\Enums\CompanyCopyPermissionType;
use App\Enums\ProductKind;
use App\Models\Period\Contact;

it('hides national identifiers from unprivileged copied-contact previews', function (): void {
    auth()->logout();
    $source = new Contact([
        'title' => 'V4 Source',
        'type' => 'legal',
        'national_id' => 'V4_TEST_IDENTIFIER_NEVER_REAL',
        'risk_limit' => '100',
    ]);
    $inspector = (new ReflectionClass(InspectCopiedRecords::class))->newInstanceWithoutConstructor();
    $snapshot = (new ReflectionMethod(InspectCopiedRecords::class, 'snapshot'))
        ->invoke($inspector, $source, CompanyCopyPermissionType::Contact, true);

    expect($snapshot)->toHaveKey('unvan', 'V4 Source')
        ->not->toHaveKey('tc_kimlik');
});

it('normalizes boolean, null and enum snapshot values deterministically for provenance diffs', function (): void {
    $inspector = (new ReflectionClass(InspectCopiedRecords::class))->newInstanceWithoutConstructor();
    $scalar = new ReflectionMethod(InspectCopiedRecords::class, 'scalar');

    expect($scalar->invoke($inspector, null))->toBe('')
        ->and($scalar->invoke($inspector, true))->toBe('1')
        ->and($scalar->invoke($inspector, false))->toBe('0')
        ->and($scalar->invoke($inspector, ProductKind::Normal))->toBe(ProductKind::Normal->value)
        ->and($scalar->invoke($inspector, '0.0000'))->toBe('0.0000');
});
