<?php

use App\Actions\Contacts\SaveContact;
use App\Actions\Contacts\SaveContactAddress;
use App\Actions\Contacts\SaveContactBank;
use App\Actions\Contacts\SaveContactPerson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('creates normalized contact records and protects immutable identity on edits', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $saved = app(SaveContact::class)->handle([
                'title' => '  V4 Customer  ',
                'type' => 'legal',
                'code' => 'OVERRIDE-UNSAFE',
                'risk_limit' => '250',
                'discount_rate' => '5',
            ]);
            expect($saved->title)->toBe('V4 Customer')
                ->and($saved->code)->toStartWith('CR')
                ->and($saved->risk_limit)->toBe('250.0000');

            $updated = app(SaveContact::class)->handle([
                'title' => '  V4 Customer Updated  ', 'type' => 'legal',
                'code' => 'CAN-NOT-CHANGE',
            ], $saved, (int) $saved->version);
            expect($updated->code)->toBe($saved->code)
                ->and($updated->title)->toBe('V4 Customer Updated');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('maintains one default address per type and one primary contact person', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $contact = app(SaveContact::class)->handle(['title' => 'V4 Contact', 'type' => 'legal']);
            $address = app(SaveContactAddress::class);
            $first = $address->handle($contact, ['type' => 'shipping', 'address' => 'A', 'is_default' => true]);
            $second = $address->handle($contact, ['type' => 'shipping', 'address' => 'B', 'is_default' => true]);

            expect($first->refresh()->is_default)->toBeFalse()
                ->and($second->is_default)->toBeTrue();

            $people = app(SaveContactPerson::class);
            $primary = $people->handle($contact, ['name' => 'One', 'is_default' => true]);
            $replacement = $people->handle($contact, ['name' => 'Two', 'is_default' => true]);

            expect($primary->refresh()->is_default)->toBeFalse()
                ->and($replacement->is_default)->toBeTrue()
                ->and($contact->fresh()->people()->count())->toBe(2);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('validates IBAN before storing supplier bank accounts', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $contact = app(SaveContact::class)->handle(['title' => 'V4 Supplier', 'type' => 'legal']);
            expect(fn () => app(SaveContactBank::class)->handle($contact, ['iban' => 'TR-INVALID']))
                ->toThrow(ValidationException::class);
            expect(DB::connection('period')->table('contact_banks')->where('contact_id', $contact->id)->count())
                ->toBe(0);

            $iban = 'TR'.str_repeat('1', 24);
            $bank = app(SaveContactBank::class)->handle($contact, [
                'iban' => 'tr '.str_repeat('1', 24), 'is_default' => true, 'bank_name' => 'V4 bank',
            ]);
            expect($bank->iban)->toBe($iban)
                ->and($bank->is_default)->toBeTrue();
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
