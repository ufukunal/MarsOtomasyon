<?php

use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Pages\Contacts\ContactList;

it('renders the approved contact list summary from period records, not synthetic prototype data', function (): void {
    $table = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $class = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactList.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect(is_subclass_of(ContactList::class, DataTableComponent::class))->toBeTrue()
        ->and($table)->toContain('v65-cari-list-stats')
        ->toContain('overviewStats()')
        ->toContain('Toplam Cari')
        ->toContain('Toplam Borç')
        ->toContain('Toplam Alacak')
        ->toContain('<x-v65.list-footer')
        ->and($class)->toContain('COUNT(*) FILTER')
        ->toContain("groupBy('contact_id')")
        ->toContain('function columnUrl')
        ->and($css)->toContain('.v65-cari-list-stats');
});

it('provides separate shipping addresses in the detail view and authorizes returns menu', function (): void {
    $class = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactDetail.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));
    expect($class)->toContain("where('type', 'shipping')")
        ->and($view)->toContain('@forelse($addresses as $address)')
        ->toContain("@can('returns.view')")
        ->not->toContain('sales_returns.view');
});
