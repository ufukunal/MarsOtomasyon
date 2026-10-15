<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Period\Document;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Spatie\Browsershot\Browsershot;

final class PrintBusinessDocumentController
{
    public function __invoke(int $document, string $format): View|Response
    {
        abort_unless(in_array($format, ['html', 'pdf'], true), 404);
        // Period selection must happen before querying period-scoped Document.
        PeriodContext::ensure();

        $record = Document::query()
            ->with(['contact', 'lines.unit', 'incomingRelations.sourceDocument'])
            ->findOrFail($document);

        $definition = match ($record->document_type) {
            DocumentType::Quote => ['Teklif', 'quotes.view'],
            DocumentType::SalesOrder => ['Satış Siparişi', 'sales_orders.view'],
            DocumentType::Dispatch => ['Satış İrsaliyesi', 'dispatches.view'],
            DocumentType::SalesInvoice => ['Satış Faturası', 'sales_invoices.view'],
            DocumentType::PurchaseOrder => ['Alış Siparişi', 'purchase_orders.view'],
            DocumentType::GoodsReceipt => ['Mal Kabul', 'goods_receipts.view'],
            DocumentType::SupplierInvoice => ['Alış Faturası', 'supplier_invoices.view'],
            default => null,
        };
        abort_unless($definition !== null, 404);
        abort_unless(auth()->user()?->can($definition[1]), 403);

        $company = Company::query()->findOrFail(PeriodContext::companyId());
        $view = view('printing.business-document', [
            'document' => $record,
            'company' => $company,
            'title' => $definition[0],
            'generatedAt' => now(),
        ]);

        if ($format === 'html') {
            return $view;
        }

        // Render stored document lines and totals only, never unsaved Livewire values.
        // This is a corporate PDF, not a legally issued e-Fatura/e-Arşiv XML.
        $pdf = Browsershot::html($view->render())
            ->format('A4')
            ->margins(12, 10, 14, 10)
            ->showBackground()
            ->pdf();

        $basename = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($record->number ?: 'belge-'.$record->id));
        $basename = trim((string) $basename, '-') ?: 'belge-'.$record->id;

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$basename.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
