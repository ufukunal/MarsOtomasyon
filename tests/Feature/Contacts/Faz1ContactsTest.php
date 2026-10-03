<?php

use App\Actions\Contacts\SaveContact;
use App\Livewire\Pages\Contacts\ContactList;
use App\Models\Period\Contact;
use App\Support\Period\PeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

it('cari kodunu otomatik benzersiz üretir ve değiştirilmesini engeller', function () {
    [$company, $period] = $this->createCompanyWithPeriod('CONTACT');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $action = app(SaveContact::class);

    $first = $action->handle([
        'title' => 'Birinci Cari',
        'type' => 'legal',
        'risk_limit' => '0',
        'discount_rate' => '0',
        'category_ids' => [],
        'is_active' => true,
    ]);

    $second = $action->handle([
        'title' => 'İkinci Cari',
        'type' => 'legal',
        'risk_limit' => '0',
        'discount_rate' => '0',
        'category_ids' => [],
        'is_active' => true,
    ]);

    expect($first->code)->toMatch('/^CR\d{7}$/')
        ->and($second->code)->toMatch('/^CR\d{7}$/')
        ->and($first->code)->not->toBe($second->code);

    $first->code = 'CR9999999';

    expect(fn () => $first->save())->toThrow(LogicException::class);
});

it('aynı otomatik cari kodu farklı fiziksel period DBlerde kullanılabilir', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('CA');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('CB');

    PeriodContext::useSystem($companyA->id, $periodA->id);
    $a = Contact::query()->create(['title' => 'A', 'type' => 'legal']);

    PeriodContext::useSystem($companyB->id, $periodB->id);
    $b = Contact::query()->create(['title' => 'B', 'type' => 'legal']);

    expect($a->code)->toBe('CR0000001')
        ->and($b->code)->toBe('CR0000001');
});

it('term_days boş bırakıldığında kartta nullable kalır ve cari fiziksel silinemez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('TERM');
    PeriodContext::useSystem($company->id, $period->id);

    $contact = Contact::query()->create([
        'title' => 'Vadeli Cari',
        'type' => 'legal',
        'term_days' => null,
    ]);

    expect($contact->term_days)->toBeNull()
        ->and(fn () => $contact->delete())->toThrow(LogicException::class);
});

it('contacts.create izni olmayan kullanıcı action ve UI üzerinden yeni cari açamaz', function () {
    [$company, $period] = $this->createCompanyWithPeriod('CONTACTAUTH');
    $warehouse = $this->createUserWithPeriodAccess($company, $period, 'Depo');
    $this->loginToPeriod($warehouse, $company, $period);

    expect(fn () => app(SaveContact::class)->handle([
        'title' => 'Yetkisiz Cari',
        'type' => 'legal',
        'risk_limit' => '0',
        'discount_rate' => '0',
        'category_ids' => [],
        'is_active' => true,
    ]))->toThrow(AuthorizationException::class);

    Livewire::test(ContactList::class)
        ->assertDontSee('Yeni Cari');
});
