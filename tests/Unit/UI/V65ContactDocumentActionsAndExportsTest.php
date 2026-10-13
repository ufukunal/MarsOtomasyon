<?php

use App\Livewire\Pages\Contacts\ContactDetail;

it('provides document actions and exports only for authorized contact-scoped tabs', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));
    $controller = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactDetail.php'));

    expect($view)->toContain('Yeni Teklif')
        ->toContain('Yeni Sipariş')
        ->toContain('Yeni Satış Faturası')
        ->toContain('exportContactDocumentsCsv')
        ->toContain('exportContactDocumentsXlsx')
        ->toContain('@can(')
        ->and($controller)->toContain('function documentKindsForTab')
        ->toContain("where('contact_id', \$this->contact->id)")
        ->toContain("documentQueryForTab(\$tab)")
        ->toContain('function exportContactDocumentsCsv')
        ->toContain('function exportContactDocumentsXlsx')
        ->toContain('DataType::TYPE_STRING')
        ->toContain("preg_match('/^[=+\\-@");
});

it('keeps approved contact address, shipping and editable page navigation separate', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));

    expect($view)->toContain("@elseif(\$activeTab === 'addresses')")
        ->toContain('@forelse($addresses as $address)')
        ->toContain('v65-contact-address-grid')
        ->toContain('v65-contact-top-actions')
        ->toContain('v65-contact-tabs')
        ->toContain('<x-v65.list-footer');
});
