<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Period\Document;
use App\Support\Period\PeriodContext;
use Illuminate\Http\Response;
use Spatie\Browsershot\Browsershot;

final class PrintBusinessDocumentController
{
    public function __invoke(int $document, string $format): Response
    {
        abort_unless(in_array($format, ['html', 'pdf'], true), 404);
        // Period selection must happen before querying period-scoped Document.
        PeriodContext::ensure();

        $record = Document::query()
            ->with(['contact', 'lines.unit', 'lines.location', 'incomingRelations.sourceDocument'])
            ->findOrFail($document);

        $definition = match ($record->document_type) {
            DocumentType::Quote => ['Teklif', 'quotes.view'],
            DocumentType::SalesOrder => ['Satış Siparişi', 'sales_orders.view'],
            DocumentType::Dispatch => ['Satış İrsaliyesi', 'dispatches.view'],
            DocumentType::SalesInvoice => ['Satış Faturası', 'sales_invoices.view'],
            DocumentType::PurchaseQuote => ['Alış Teklifi', 'purchase_quotes.view'],
            DocumentType::PurchaseOrder => ['Alış Siparişi', 'purchase_orders.view'],
            DocumentType::PurchaseDispatch => ['Alış İrsaliyesi', 'purchase_dispatches.view'],
            DocumentType::GoodsReceipt => ['Mal Kabul', 'goods_receipts.view'],
            DocumentType::SupplierInvoice => ['Alış Faturası', 'supplier_invoices.view'],
            default => null,
        };
        abort_unless($definition !== null, 404);
        abort_unless(auth()->user()?->can($definition[1]), 403);

        $company = Company::query()->findOrFail(PeriodContext::companyId());

        // Source reference numbers are visible only with that document type's permission.
        $sourceReferences = $record->incomingRelations
            ->map(static function ($relation): ?string {
                $source = $relation->sourceDocument;
                if ($source === null) {
                    return null;
                }

                $permission = match ($source->document_type) {
                    DocumentType::Quote => 'quotes.view',
                    DocumentType::SalesOrder => 'sales_orders.view',
                    DocumentType::Dispatch => 'dispatches.view',
                    DocumentType::SalesInvoice => 'sales_invoices.view',
                    DocumentType::PurchaseQuote => 'purchase_quotes.view',
                    DocumentType::PurchaseOrder => 'purchase_orders.view',
                    DocumentType::PurchaseDispatch => 'purchase_dispatches.view',
                    DocumentType::GoodsReceipt => 'goods_receipts.view',
                    DocumentType::SupplierInvoice => 'supplier_invoices.view',
                    default => null,
                };

                if ($permission === null || ! auth()->user()?->can($permission)) {
                    return null;
                }

                return (string) ($source->number ?? 'Taslak #'.$source->id);
            })
            ->filter()
            ->values();

        // These are presentation aggregates from persisted line discounts,
        // not a new calculation of VAT, allocations or payable amount.
        $lineDiscountAmount = '0.0000';
        foreach ($record->lines as $line) {
            $lineDiscountAmount = bcadd($lineDiscountAmount, (string) $line->line_discount_amount, 4);
        }
        $grossTotal = bcadd((string) $record->subtotal, $lineDiscountAmount, 4);

        $view = view('printing.business-document', [
            'document' => $record,
            'company' => $company,
            'title' => $definition[0],
            'generatedAt' => now(),
            'grossTotal' => $grossTotal,
            'lineDiscountAmount' => $lineDiscountAmount,
            'sourceReferences' => $sourceReferences,
        ]);

        if ($format === 'html') {
            return response($view->render(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        // Render stored document lines and totals only, never unsaved Livewire values.
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
