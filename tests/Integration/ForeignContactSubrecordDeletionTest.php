<?php

use App\Actions\Contacts\DeleteContactAddress;
use App\Actions\Contacts\DeleteContactBank;
use App\Actions\Contacts\DeleteContactPerson;
use App\Models\Period\Contact;
use App\Models\Period\ContactAddress;
use App\Models\Period\ContactBank;
use App\Models\Period\ContactPerson;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('refuses deleting a subrecord belonging to another contact before any SQL delete', function (string $action, string $model): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $target = new Contact;
            $target->id = 1001;
            $subrecord = new $model;
            $subrecord->id = 2011;
            $subrecord->contact_id = 2002;

            expect(fn () => app($action)->handle($target, $subrecord, 1))
                ->toThrow(HttpException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
})->with([
    'address' => [DeleteContactAddress::class, ContactAddress::class],
    'bank' => [DeleteContactBank::class, ContactBank::class],
    'person' => [DeleteContactPerson::class, ContactPerson::class],
]);
