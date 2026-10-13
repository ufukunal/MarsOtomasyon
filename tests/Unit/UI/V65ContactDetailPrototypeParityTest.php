<?php

use App\Livewire\Pages\Contacts\ContactDetail;
use App\Models\Period\Contact;
use Illuminate\Support\Facades\Route;

it('has a permissioned contact details route distinct from contact edit', function (): void {
    expect(Route::has('contacts.show'))->toBeTrue()
        ->and(Route::has('contacts.edit'))->toBeTrue()
        ->and(is_subclass_of(ContactDetail::class, \Livewire\Component::class))->toBeTrue();
});

it('shows approved contact-detail tabs but no fabricated b2b metrics', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));
    $component = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactDetail.php'));

    expect($view)->toContain('v65-contact-tabs')
        ->toContain('v65-contact-metrics')
        ->toContain('v65-contact-overview-grid')
        ->toContain('Son Cari Hareketler')
        ->toContain('Devam Eden İşler')
        ->toContain('İletişim Özeti')
        ->toContain('B2B / Bayi')
        ->toContain('B2B giriş sayısı, oturum')
        ->toContain('<x-v65.list-footer')
        ->not->toContain('148.150,00')
        ->and($component)->toContain("authorize('view', \$contact)")
        ->toContain('ContactTransaction::query()')
        ->toContain("where('contact_id', \$contact->id)")
        ->toContain("where('status', 'posted')")
        ->toContain('groupByRaw(')
        ->toContain('paginate($this->perPage)')
        ->toContain("selectTab(string \$tab)");
});

it('preserves the agreed shared list toolbar while opening contact details by name/code', function (): void {
    $list = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactList.php'));
    $table = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));

    expect($list)->toContain("route('contacts.show'")
        ->not->toContain("'label' => 'Aç'")
        ->not->toContain("'label' => 'Düzenle'")
        ->and($table)->toContain('<x-v65.list-footer')
        ->toContain("@include('livewire.components.partials.v65-table-filter-controls')")
        ->toContain('v65-data-table-heading-actions');
});

it('does not duplicate print/back controls in the contact detail header', function (): void {
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));

    expect($layout)->toContain("@elseif (\$routeName === 'contacts.show')")
        ->toContain("'contacts.show'], true)")
        ->and($view)->toContain('v65-contact-top-actions')
        ->toContain('onclick="window.print()"')
        ->toContain("route('contacts.index')");
});
