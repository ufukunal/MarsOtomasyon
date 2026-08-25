<?php

namespace App\Modules\Quotes;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Quotes\Actions\CreateQuoteRevision;
use App\Modules\Quotes\Models\QuoteRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final readonly class QuoteRevisionController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateQuoteRevision $createRevision,
    ) {}

    public function store(int $quote): RedirectResponse
    {
        $revision = $this->createRevision->handle($quote);

        return redirect()
            ->route('quotes.revisions.show', [$quote, $revision->getKey()])
            ->with('status', 'Teklif revizyon snapshotı hazırlandı.');
    }

    public function show(int $quote, int $revision): View
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $revisionModel = QuoteRevision::query()
            ->where('company_id', $companyId)
            ->where('quote_id', $quote)
            ->whereKey($revision)
            ->with('lines')
            ->firstOrFail();

        return view('quotes.revisions.show', ['revision' => $revisionModel]);
    }
}
