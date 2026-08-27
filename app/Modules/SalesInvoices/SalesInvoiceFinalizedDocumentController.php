<?php

namespace App\Modules\SalesInvoices;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\FileAsset;
use App\Modules\SalesInvoices\Documents\SalesInvoiceFinalizedDocumentService;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesInvoices\Models\SalesInvoiceEDocumentEvent;
use App\Modules\SalesInvoices\Models\SalesInvoiceFinalizedDocument;
use Illuminate\Http\Response;
use Illuminate\View\View;

final readonly class SalesInvoiceFinalizedDocumentController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private SalesInvoiceFinalizedDocumentService $documents,
    ) {}

    public function show(int $salesInvoice): View
    {
        $invoice = $this->finalizedInvoice($salesInvoice);
        $document = SalesInvoiceFinalizedDocument::query()
            ->where('company_id', $this->companyId())
            ->where('sales_invoice_id', $salesInvoice)
            ->where('renderer_version', SalesInvoiceFinalizedDocumentService::RENDERER_VERSION)
            ->with('fileAsset')
            ->first();

        return view('sales-invoices.finalized.show', [
            'invoice' => $invoice,
            'document' => $document,
            'rendererVersion' => SalesInvoiceFinalizedDocumentService::RENDERER_VERSION,
            'eDocumentEvents' => SalesInvoiceEDocumentEvent::query()
                ->where('company_id', $this->companyId())
                ->where('sales_invoice_id', $salesInvoice)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function download(int $salesInvoice): Response
    {
        $this->finalizedInvoice($salesInvoice);
        $document = $this->documents->getOrCreate($salesInvoice);
        $bytes = $this->documents->verifiedBytes($document);
        $asset = $document->fileAsset;
        abort_unless($asset instanceof FileAsset, 410, 'Finalized invoice PDF metadata is unavailable.');

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.(string) $asset->original_name.'"',
            'Content-Length' => (string) strlen($bytes),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Document-Version' => (string) $document->renderer_version,
            'X-Document-SHA256' => (string) $document->pdf_sha256,
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function finalizedInvoice(int $invoiceId): SalesInvoice
    {
        $invoice = SalesInvoice::query()
            ->where('company_id', $this->companyId())
            ->whereKey($invoiceId)
            ->with(['company', 'account', 'sourceSalesOrder', 'sourceDispatch', 'lines.warehouse', 'lines.location'])
            ->firstOrFail();

        if (! in_array($invoice->statusEnum(), [SalesInvoiceStatus::Finalized, SalesInvoiceStatus::Cancelled], true)) {
            abort(409, 'Finalized görünüm yalnız kesinleşmiş veya iptal edilmiş satış faturaları için kullanılabilir.');
        }

        return $invoice;
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
