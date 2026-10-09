<?php

use App\Actions\Contacts\SaveContact;
use App\Models\Period\Contact;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2CONTACT');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
});

it('v2 contact code sequence is unique across consecutive writes within one period', function () {
    $a = app(SaveContact::class)->handle(['title' => 'Company A', 'type' => 'legal']);
    $b = app(SaveContact::class)->handle(['title' => 'Company B', 'type' => 'legal']);
    expect($a->code)->not->toBe($b->code)
        ->and(Contact::query()->count())->toBe(2);
});

it('v2 contact code cannot be changed after creation', function () {
    $contact = Contact::query()->create(['title' => 'Immutable Contact', 'type' => 'legal']);
    expect(fn () => $contact->update(['code' => 'FORGED']))->toThrow(LogicException::class);
    expect($contact->refresh()->code)->not->toBe('FORGED');
});

it('v2 physical contact deletion is forbidden even without linked transactions', function () {
    $contact = Contact::query()->create(['title' => 'Protected Contact', 'type' => 'legal']);
    expect(fn () => $contact->delete())->toThrow(LogicException::class);
    expect(Contact::query()->whereKey($contact->id)->exists())->toBeTrue();
});

it('v2 contact API accepts no caller-provided fake contact code', function () {
    $contact = app(SaveContact::class)->handle(['title' => 'Code Guard', 'type' => 'legal', 'code' => 'CUSTOM-FORGE']);
    expect($contact->code)->not->toBe('CUSTOM-FORGE');
});
