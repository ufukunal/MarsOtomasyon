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
        ->toContain('.v65-cari-list .data-table .v65-cari-balance.is-receivable')
        ->toContain('.v65-cari-list .data-table .v65-cari-balance.is-payable')
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

it('formats real TRY ledger net as receivable, payable, and risk percent', function (): void {
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
        ->and($list->formattedValue($contact, Column::make('balance_display', 'Bakiye')))->toBe('50,00 ₺ Alacaklı')
        ->and($list->formattedValue($contact, Column::make('risk_usage', 'Risk Kullanımı')))->toBe('25%')
        ->and($list->formattedValue($contact, Column::make('last_movement', 'Son Hareket')))->toBe('23.08.2026')
        ->and($list->formattedValue($contact, Column::make('is_active', 'Durum')))->toBe('Aktif');

    $contact->setAttribute('v65_try_net', '-92.4000');
    expect($list->formattedValue($contact, Column::make('balance_display', 'Bakiye')))->toBe('92,40 ₺ Borçlu')
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
