<?php

use App\Livewire\Pages\Contacts\ContactDetail;
use App\Livewire\Pages\Contacts\ContactList;
use Illuminate\Support\Facades\Route;

it('keeps a distinct authorized customer-detail page and the shared list standard', function (): void {
    $list = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactList.php'));
    $table = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $detail = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactDetail.php'));

    expect(Route::has('contacts.show'))->toBeTrue()
        ->and(is_subclass_of(ContactList::class, \App\Livewire\Components\DataTable\DataTableComponent::class))->toBeTrue()
        ->and(is_subclass_of(ContactDetail::class, \Livewire\Component::class))->toBeTrue()
        ->and($list)->not->toContain("'label' => 'Aç'")
        ->toContain("route('contacts.show'")
        ->toContain("where('currency', 'TRY')")
        ->and($table)->toContain('v65-data-table-heading-actions')
        ->toContain("@include('livewire.components.partials.v65-table-filter-controls')")
        ->toContain('<x-v65.list-footer')
        ->toContain('v65-cari-list-stats')
        ->not->toContain('148.150,00')
        ->and($detail)->toContain("authorize('view', \$contact)");
});

it('builds accounting statements from chronological ledger data with per-currency running totals', function (): void {
    $class = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactDetail.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));

    expect($class)->toContain('OVER (PARTITION BY currency ORDER BY transaction_date, id')
        ->toContain("whereDate('transaction_date', '<', \$from)")
        ->toContain("setAttribute('running_balance'")
        ->toContain("whereDate('transaction_date', '>=', \$from)")
        ->toContain("whereDate('transaction_date', '<=', \$to)")
        ->toContain('public function exportStatementCsv()')
        ->toContain('public function exportStatementXlsx()')
        ->toContain('DataType::TYPE_STRING')
        ->toContain('DateTimeImmutable::createFromFormat')
        ->and($view)->toContain("switchStatementMode('vouchers')")
        ->toContain("switchStatementMode('summary')")
        ->toContain("switchStatementMode('detailed')")
        ->toContain('wire:model.live.debounce.300ms="statementFrom"')
        ->toContain('wire:model.live.debounce.300ms="statementTo"')
        ->toContain('wire:click="exportStatementCsv"')
        ->toContain('wire:click="exportStatementXlsx"');
});

it('uses real posted invoice data for analysis and never substitutes prototype revenue', function (): void {
    $class = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactDetail.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));

    expect($class)->toContain("where('status', 'posted')")
        ->toContain("where('currency', 'TRY')")
        ->toContain("sum('line.line_total')")
        ->toContain('productCategories')
        ->toContain('recentProductLines')
        ->toContain('EXTRACT(MONTH FROM document_date)')
        ->and($view)->toContain('En Çok Alınan Ürünler')
        ->toContain('Kategori Dağılımı')
        ->toContain('Son Ürün Hareketleri')
        ->toContain('Aylık Performans')
        ->toContain('Geçerlilik')
        ->toContain('v65-contact-address-grid')
        ->toContain('Firma İletişim Kanalları')
        ->toContain('Fatura Bilgileri')
        ->toContain('Sevk Bilgileri')
        ->toContain('Banka Bilgileri')
        ->not->toContain('682.400,00');
});

it('uses a contact-specific layout without changing product and warehouse list headers', function (): void {
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));

    expect($css)->toContain('.app-shell .v65-contact-tabs')
        ->toContain('.app-shell .v65-contact-two-column')
        ->toContain('.app-shell .v65-contact-people-list')
        ->toContain('.app-shell .v65-contact-statement-filter')
        ->toContain('.app-shell .v65-contact-invoice-info')
        ->and($view)->toContain("@teleport('.page-actions.v65-page-actions')")
        ->toContain("route('contacts.edit'")
        ->toContain('İade İşlemleri')
        ->toContain('Alım İşlemleri');
});
