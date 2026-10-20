<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\BusinessDocumentPrintVariant;
use App\Models\Company;
use App\Models\Period\Document;
use App\Support\Period\PeriodContext;
use App\Support\Printing\BusinessDocumentImageResolver;
use App\Support\Printing\Label\OpaqueQrSvg;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

final class PrintBusinessDocumentController
{
    public function __invoke(int $document, string $format): Response
    {
        abort_unless(in_array($format, ['html', 'pdf'], true), 404);
        // Period selection must happen before querying period-scoped Document.
        PeriodContext::ensure();

        $record = Document::query()
            ->with(['contact', 'lines.unit', 'lines.product', 'lines.location', 'incomingRelations.sourceDocument'])
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

        $variantValue = request()->query('variant', BusinessDocumentPrintVariant::PlainPriced->value);
        $printVariant = is_string($variantValue)
            ? BusinessDocumentPrintVariant::tryFrom($variantValue)
            : null;
        abort_unless($printVariant !== null, 422, 'Geçersiz yazdırma tasarımı.');

        // Inline thumbnails are used for both HTML preview and PDF.
        $productImages = $printVariant->showImages()
            ? app(BusinessDocumentImageResolver::class)->forDocument($record)
            : [];
        $customImages = $printVariant->showImages()
            ? app(BusinessDocumentImageResolver::class)->forCustomDocument($record)
            : [];

        $meta = is_array($record->ui_meta) ? $record->ui_meta : [];
        $record->contact?->loadMissing('addresses');
        $contactAddresses = $record->contact?->addresses;
        $billAddress = $contactAddresses?->where('type', 'invoice')
            ->sortByDesc('is_default')->first();
        $shipAddress = $contactAddresses?->where('type', 'shipping')
            ->sortByDesc('is_default')->first();
        $delivery = [
            'company' => (string) ($meta['delivery_company'] ?? $record->contact?->title ?? ''),
            'person' => (string) ($meta['delivery_person'] ?? ''),
            'phone' => (string) ($meta['delivery_phone'] ?? ''),
            'address' => (string) ($meta['delivery_address'] ?? $shipAddress?->address ?? $record->contact?->address ?? ''),
            'city' => (string) ($meta['delivery_city'] ?? $shipAddress?->city ?? $record->contact?->city ?? ''),
            'district' => (string) ($meta['delivery_district'] ?? $shipAddress?->district ?? $record->contact?->district ?? ''),
        ];
        $billing = [
            'address' => (string) ($billAddress?->address ?? $record->contact?->address ?? ''),
            'city' => (string) ($billAddress?->city ?? $record->contact?->city ?? ''),
            'district' => (string) ($billAddress?->district ?? $record->contact?->district ?? ''),
        ];
        $qrScope = implode(':', [
            (string) PeriodContext::companyId(),
            (string) PeriodContext::periodId(),
            $record->document_type->value,
            (string) $record->id,
        ]);
        $documentQrToken = OpaqueQrSvg::token($qrScope, 0, (string) config('app.key'));

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
        // Both line and document discounts contribute to the displayed total discount.
        // This is only a display aggregate; persisted VAT and grand totals are authoritative.
        $totalDiscountAmount = bcadd($lineDiscountAmount, (string) $record->discount_amount, 4);

        $view = view('printing.business-document', [
            'document' => $record,
            'company' => $company,
            'title' => $definition[0],
            'generatedAt' => now(),
            'grossTotal' => $grossTotal,
            'lineDiscountAmount' => $lineDiscountAmount,
            'totalDiscountAmount' => $totalDiscountAmount,
            'sourceReferences' => $sourceReferences,
            'printVariant' => $printVariant,
            'showPrices' => $printVariant->showPrices(),
            'showImages' => $printVariant->showImages(),
            'productImages' => $productImages,
            'customImages' => $customImages,
            'billing' => $billing,
            'delivery' => $delivery,
            'documentQrToken' => $documentQrToken,
        ]);

        $html = $view->render();
        if ($format === 'html') {
            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        // Print the same fully rendered, self-contained HTML as the selected preview.
        // System Chromium is installed in the UAT image, without a Puppeteer runtime.
        if (! is_executable('/usr/bin/chromium')) {
            Log::error('Business document PDF runtime missing Chromium');
            abort(503, 'PDF servisi kullanılamıyor. Sunucu tarayıcı bileşeni eksik.');
        }
        $workDir = sys_get_temp_dir().'/mars-document-pdf-'.bin2hex(random_bytes(12));
        if (! File::makeDirectory($workDir, 0700)) {
            abort(503, 'PDF geçici çalışma alanı oluşturulamadı.');
        }

        $pdf = '';
        try {
            $htmlFile = $workDir.'/document.html';
            $pdfFile = $workDir.'/document.pdf';
            if (file_put_contents($htmlFile, $html) === false) {
                abort(503, 'PDF oluşturulamadı.');
            }

            $process = new Process([
                '/usr/bin/chromium',
                '--headless',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-extensions',
                '--disable-background-networking',
                '--no-pdf-header-footer',
                '--user-data-dir='.$workDir.'/profile',
                '--print-to-pdf='.$pdfFile,
                'file://'.$htmlFile,
            ], null, ['HOME' => $workDir, 'XDG_CONFIG_HOME' => $workDir]);
            $process->setTimeout(60);
            try {
                $process->run();
            } catch (\Symfony\Component\Process\Exception\ExceptionInterface $error) {
                Log::error('Business document PDF process could not start or timed out', [
                    'document_type' => $record->document_type->value,
                    'variant' => $printVariant->value,
                    'message' => mb_substr($error->getMessage(), 0, 800),
                ]);
                abort(503, 'PDF oluşturulamadı. Yazdırma servisini kontrol edin.');
            }
            $pdf = is_file($pdfFile) ? file_get_contents($pdfFile) : false;

            if (! $process->isSuccessful() || ! is_string($pdf) || ! str_starts_with($pdf, '%PDF-')) {
                Log::error('Business document PDF rendering failed', [
                    'document_type' => $record->document_type->value,
                    'variant' => $printVariant->value,
                    'exit_code' => $process->getExitCode(),
                    'stderr' => mb_substr($process->getErrorOutput(), 0, 800),
                ]);
                abort(503, 'PDF oluşturulamadı. Yazdırma servisini kontrol edin.');
            }
        } finally {
            File::deleteDirectory($workDir);
        }

        $basename = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($record->number ?: 'belge-'.$record->id));
        $basename = (trim((string) $basename, '-') ?: 'belge-'.$record->id).'-'.$printVariant->value;

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$basename.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
