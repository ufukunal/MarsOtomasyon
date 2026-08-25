<?php

namespace App\Modules\Quotes;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Quotes\Documents\QuoteFinalizedDocumentService;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteFinalizedDocument;
use Illuminate\Http\Response;
use Illuminate\View\View;

final readonly class QuoteFinalizedDocumentController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private QuoteFinalizedDocumentService $documents,
    ) {}

    public function show(int $quote): View
    {
        $quoteModel = $this->finalizedQuote($quote);
        $document = QuoteFinalizedDocument::query()
            ->where('company_id', $this->companyId())
            ->where('quote_id', $quote)
            ->where('renderer_version', QuoteFinalizedDocumentService::RENDERER_VERSION)
            ->with('fileAsset')
            ->first();

        return view('quotes.finalized.show', [
            'quote' => $quoteModel,
            'revision' => $quoteModel->selectedRevision,
            'document' => $document,
            'rendererVersion' => QuoteFinalizedDocumentService::RENDERER_VERSION,
        ]);
    }

    public function download(int $quote): Response
    {
        $this->finalizedQuote($quote);
        $document = $this->documents->getOrCreate($quote);
        $bytes = $this->documents->verifiedBytes($document);
        $asset = $document->fileAsset;
        abort_unless($asset instanceof FileAsset, 410, 'Finalized PDF file metadata is unavailable.');

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

    private function finalizedQuote(int $quoteId): Quote
    {
        $quote = Quote::query()
            ->where('company_id', $this->companyId())
            ->whereKey($quoteId)
            ->with(['company', 'account', 'selectedRevision.lines', 'decisionBy', 'salesOrder'])
            ->firstOrFail();

        if (! in_array($quote->statusEnum(), [QuoteStatus::Approved, QuoteStatus::Rejected, QuoteStatus::Converted], true)
            || $quote->selected_revision_id === null
            || $quote->selectedRevision === null) {
            abort(409, 'Finalized görünüm yalnız seçili immutable revision bulunan sonuçlanmış teklifler için kullanılabilir.');
        }

        return $quote;
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
