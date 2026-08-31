<?php

namespace App\Modules\B2B\Portal;

use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\Core\Models\FileAsset;
use App\Modules\SalesInvoices\Documents\SalesInvoiceFinalizedDocumentService;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use Illuminate\Http\Response;
use Illuminate\View\View;

final readonly class B2BInvoiceController
{
    public function __construct(private B2BPortalAccess $access, private SalesInvoiceFinalizedDocumentService $documents) {}

    public function index(): View
    {
        $this->access->authorize(B2BPermission::ViewInvoices);
        $user = $this->access->user();
        $invoices = SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('account_id', $user->account_id)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(30);

        return view('b2b.invoices.index', compact('invoices'));
    }

    public function show(string $invoice): View
    {
        $invoiceModel = $this->invoice($invoice);
        $invoiceModel->load('lines');

        return view('b2b.invoices.show', ['invoice' => $invoiceModel]);
    }

    public function download(string $invoice): Response
    {
        $invoiceModel = $this->invoice($invoice);
        abort_unless(in_array($invoiceModel->statusEnum(), [SalesInvoiceStatus::Finalized, SalesInvoiceStatus::Cancelled], true), 409);

        $document = $this->documents->getOrCreate((int) $invoiceModel->getKey());
        $bytes = $this->documents->verifiedBytes($document);
        $asset = $document->fileAsset;
        abort_unless($asset instanceof FileAsset, 410);

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

    private function invoice(string $number): SalesInvoice
    {
        $this->access->authorize(B2BPermission::ViewInvoices);
        $user = $this->access->user();

        return SalesInvoice::query()
            ->where('company_id', $user->company_id)
            ->where('account_id', $user->account_id)
            ->where('number', trim($number))
            ->firstOrFail();
    }
}
