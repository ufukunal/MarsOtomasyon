<?php

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Pages\Contacts\ContactList;
use App\Models\Period\Contact;
use App\Models\Period\ContactCategory;

it('renders the approved six-card cari summary in the correct order without fabricated figures', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $class = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactList.php'));

    $labels = ['Toplam Cari', 'Müşteri', 'Tedarikçi', 'Toplam Alacak', 'Toplam Borç', 'Aktif'];
    $previous = strpos($view, 'v65-cari-list-stats');

    foreach ($labels as $label) {
        $index = strpos($view, '<small>'.$label.'</small>', $previous);
        expect($index)->not->toBeFalse();
        $previous = $index + strlen($label);
    }

    expect($view)->toContain("['customers']")
        ->toContain("['suppliers']")
        ->toContain('v65-cari-kpi-credit')
        ->toContain('v65-cari-kpi-debit')
        ->toContain('Müşteri ve tedarikçi kartları')
        ->not->toContain('<small>Pasif</small>')
        ->not->toContain('<small>Şehir</small>')
        ->and($class)->toContain("whereHas('categories'")
        ->toContain("->where('name', 'ilike', 'Müşteri')")
        ->toContain("->where('name', 'ilike', 'Tedarikçi')")
        ->toContain("where('currency', 'TRY')");
});

it('keeps the existing top-level actions while showing the filter drawer and linked contact fields', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($view)->toContain("'v65-cari-list' =>")
        ->toContain("route(\$emptyAction['route'])")
        ->toContain('wire:click="exportCsv"')
        ->toContain('wire:click="exportXlsx"')
        ->toContain("@include('livewire.components.partials.v65-table-filter-controls')")
        ->toContain('v65-cari-record-title')
        ->toContain('v65-cari-balance')
        ->toContain('v65-cari-state')
        ->toContain('wire:click="toggleVisibleContacts"')
        ->and($css)->toContain('.v65-cari-list .v65-cari-list-stats')
        ->toContain('.v65-cari-list .data-table .v65-cari-balance.is-debtor')
        ->toContain('.v65-cari-list .data-table .v65-cari-balance.is-creditor')
        ->toContain('grid-template-columns:repeat(2,minmax(0,1fr))');
});

it('exposes only real contact and ledger fields in the reference table order', function (): void {
    $list = new ContactList;
    $keys = collect($list->columns())->pluck('key')->all();
    expect($keys)->toBe([
        'code', 'title', 'primary_contact_name', 'category_names', 'city_district',
        'phone', 'tax_number', 'balance_display', 'risk_usage', 'last_movement', 'is_active',
    ]);

    $source = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactList.php'));
    expect($source)->toContain("selectRaw('MAX(transaction_date)')")
        ->toContain("'v65_last_movement'")
        ->toContain("'v65_try_net'")
        ->toContain("'contacts.id'")
        ->toContain('function toggleVisibleContacts()');
});

it('formats positive TRY debit as customer debt and negative TRY credit as customer receivable, retaining risk percent', function (): void {
    $list = new ContactList;
    $contact = new Contact;
    $contact->forceFill([
        'city' => 'İstanbul', 'district' => 'Ümraniye', 'risk_limit' => '200.0000',
        'v65_try_net' => '50.0000', 'v65_last_movement' => '2026-08-23', 'is_active' => true,
    ]);
    $contact->setRelation('categories', collect([
        (new ContactCategory)->forceFill(['name' => 'Müşteri']),
        (new ContactCategory)->forceFill(['name' => 'Tedarikçi']),
    ]));

    expect($list->formattedValue($contact, Column::make('city_district', 'İl / İlçe')))->toBe('İstanbul / Ümraniye')
        ->and($list->formattedValue($contact, Column::make('category_names', 'Tip')))->toBe('Müşteri + Tedarikçi')
        ->and($list->formattedValue($contact, Column::make('balance_display', 'Bakiye')))->toBe('50,00 ₺ Borçlu')
        ->and($list->formattedValue($contact, Column::make('risk_usage', 'Risk Kullanımı')))->toBe('25%')
        ->and($list->formattedValue($contact, Column::make('last_movement', 'Son Hareket')))->toBe('23.08.2026')
        ->and($list->formattedValue($contact, Column::make('is_active', 'Durum')))->toBe('Aktif');

    $contact->setAttribute('v65_try_net', '-92.4000');
    expect($list->formattedValue($contact, Column::make('balance_display', 'Bakiye')))->toBe('92,40 ₺ Alacaklı')
        ->and($list->formattedValue($contact, Column::make('risk_usage', 'Risk Kullanımı')))->toBe('0%');

    $contact->setAttribute('risk_limit', '0');
    expect($list->formattedValue($contact, Column::make('risk_usage', 'Risk Kullanımı')))->toBe('—');
});


it('uses the supplied blue-edge three-line KPI card design only on the cari list', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    $expectedNotes = [
        'Tüm cari kartları', 'Müşteri kartları', 'Tedarikçi kartları',
        'TRY bazında', 'Aktif cari kartları',
    ];
    foreach ($expectedNotes as $note) {
        expect($view)->toContain('<span class="v65-cari-kpi-note">'.$note.'</span>');
    }

    $section = substr($view, strpos($view, '<div class="v65-cari-list-stats">'));
    $section = substr($section, 0, strpos($section, '</section>'));
    expect(substr_count($section, '<div><small>'))->toBe(6)
        ->and(substr_count($section, 'class="v65-cari-kpi-note"'))->toBe(6)
        ->and($css)->toContain('border-left:4px solid #1877e9')
        ->toContain('grid-template-rows:auto auto auto')
        ->toContain('border-radius:0;background:#fff')
        ->toContain('font-variant-numeric:tabular-nums')
        ->toContain('.app-shell .v65-cari-list .v65-cari-list-stats .v65-cari-kpi-note');
});


it('places the only Cari drawer beside the heading and groups export actions', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $partial = file_get_contents(base_path('resources/views/livewire/components/partials/v65-table-filter-controls.blade.php'));
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    $start = strpos($view, 'class="v65-cari-heading-actions"');
    $end = strpos($view, '</details>', $start);
    $menu = substr($view, $start, $end - $start);
    expect(strpos($menu, '+ Yeni Cari'))->toBeLessThan(strpos($menu, '>Yazdır</button>'))
        ->and(strpos($menu, '>Yazdır</button>'))->toBeLessThan(strpos($menu, 'Dışa Aktar'))
        ->and($menu)->toContain('wire:click="exportCsv"')
        ->toContain('wire:click="exportXlsx"')
        ->and($view)->toContain('class="v65-cari-intro-filters"')
        ->toContain("@include('livewire.components.partials.v65-table-filter-controls')")
        ->and($partial)->toContain('<x-v65.filter-drawer')
        ->and($layout)->toContain("'contacts.show', 'contacts.index'")
        ->and($css)->toContain('.v65-cari-export-menu:not([open])>.v65-cari-export-options{display:none!important}');
});

it('only flags a customer with a positive TRY risk limit and excess receivable', function (): void {
    $list = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactList.php'));
    $invoice = file_get_contents(base_path('app/Actions/Sales/PostSalesInvoice.php'));
    $editor = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));

    expect($list)->toContain("where('contacts.risk_limit', '>', 0)")
        ->toContain("ct.currency = 'TRY'")
        ->and($invoice)->toContain('knownLimitExceeded && ! $riskAccepted')
        ->toContain("currency === 'TRY'")
        ->and($editor)->toContain('Risk limitinin aşıldığını kabul ediyorum')
        ->toContain('invoiceRiskProjection()');
});


it('uses the cari perspective consistently in totals, balance cells and detail identity without altering ledger signs', function (): void {
    $listClass = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactList.php'));
    $listView = file_get_contents(base_path('resources/views/livewire/components/data-table.blade.php'));
    $detailView = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));
    $styles = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($listClass)->toContain("'debit' => \$debit")
        ->toContain("'credit' => \$credit")
        ->toContain("1 => ' Borçlu'")
        ->toContain("-1 => ' Alacaklı'")
        ->toContain("bccomp(\$balance, '0', 4) > 0 ? \$balance : '0'")
        ->and($listView)->toContain("'is-debtor' => bccomp(\$cariNet, '0', 4) > 0")
        ->toContain("'is-creditor' => bccomp(\$cariNet, '0', 4) < 0")
        ->toContain("['credit']")
        ->toContain("['debit']")
        ->and($detailView)->toContain("'is-debit' => bccomp(\$balance, '0', 4) > 0")
        ->toContain("'is-credit' => bccomp(\$balance, '0', 4) < 0")
        ->toContain("? 'Borçlu' : (bccomp(\$balance, '0', 4) < 0 ? 'Alacaklı' : 'Dengede')")
        ->and($styles)->toContain('.v65-cari-balance.is-debtor{color:#d5232e}')
        ->toContain('.v65-cari-balance.is-creditor{color:#008744}')
        ->toContain('.v65-contact-metric strong.is-debit{color:#d5232e}')
        ->toContain('.v65-contact-metric strong.is-credit{color:#008744}');
});
