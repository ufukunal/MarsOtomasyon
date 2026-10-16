<?php

use App\Enums\DocumentType;
use App\Livewire\Components\DataTable\Column;
use App\Support\Formatting\BusinessLabelFormatter as Labels;
use App\Support\Formatting\TableValueFormatter;

it('shows Turkish labels while retaining original typed codes for storage and filtering', function (): void {
    expect(Labels::contactTransaction('opening'))->toBe('Açılış Bakiyesi')
        ->and(Labels::stockMovement('opening'))->toBe('Açılış Stok Girişi')
        ->and(Labels::documentType(DocumentType::SalesInvoice))->toBe('Satış Faturası')
        ->and(Labels::direction('debit'))->toBe('Borç')
        ->and(Labels::direction('credit'))->toBe('Alacak')
        ->and(Labels::value('security_kind', 'note'))->toBe('Senet')
        ->and(Labels::value('reservation_status', 'released'))->toBe('Rezervi Çözüldü')
        ->and(Labels::value('location_kind', 'warehouse'))->toBe('Depo')
        ->and(Labels::status('done'))->toBe('Tamamlandı')
        ->and(Labels::status('unknown'))->toBe('Bilinmiyor');
});

it('formats only explicitly marked table columns, including CSV and Excel tables', function (): void {
    $status = Column::make('status', 'Durum')->labels('status');
    $type = Column::make('document_type', 'Belge Türü')->labels('document_type');
    $raw = Column::make('document_type', 'Teknik Kod');

    expect(TableValueFormatter::format($status, 'posted'))->toBe('Kesinleşti')
        ->and(TableValueFormatter::format($type, 'sales_invoice'))->toBe('Satış Faturası')
        ->and(TableValueFormatter::format($raw, 'sales_invoice'))->toBe('sales_invoice');
});

it('translates report display values uniformly in screen, csv, xlsx and pdf without modifying filter keys', function (): void {
    $center = file_get_contents(base_path('app/Livewire/Reporting/ReportCenter.php'));
    $csv = file_get_contents(base_path('app/Support/Reporting/Export/CsvReportExporter.php'));
    $xlsx = file_get_contents(base_path('app/Support/Reporting/Export/XlsxReportExporter.php'));
    $pdf = file_get_contents(base_path('resources/views/reporting/exports/pdf.blade.php'));

    foreach ([$center, $csv, $xlsx, $pdf] as $source) {
        expect($source)->toContain('BusinessLabelFormatter::report(');
    }

    expect(Labels::report('finance.contact_transactions', 'transaction_type', 'opening'))->toBe('Açılış Bakiyesi')
        ->and(Labels::report('sales.invoices', 'document_type', 'sales_invoice'))->toBe('Satış Faturası')
        ->and(Labels::report('finance.contact_transactions', 'amount', '1234.5678'))->toBe('1234.5678');
});
