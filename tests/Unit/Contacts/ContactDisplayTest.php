<?php

use App\Models\Period\Contact;
use App\Models\Period\ContactPerson;
use Illuminate\Database\Eloquent\Collection;

it('selects the default contact person without hitting the period database', function (): void {
    $contact = new Contact;
    $contact->setRelation('people', new Collection([
        new ContactPerson(['name' => 'Secondary', 'is_default' => false]),
        new ContactPerson(['name' => 'Primary', 'is_default' => true]),
    ]));
    expect($contact->primary_contact_name)->toBe('Primary');
});

it('uses the first contact or empty string when no default is selected', function (): void {
    $contact = new Contact;
    $contact->setRelation('people', new Collection([new ContactPerson(['name' => 'First'])]));
    expect($contact->primary_contact_name)->toBe('First');
    $contact->setRelation('people', new Collection);
    expect($contact->primary_contact_name)->toBe('');
});
