<?php

namespace App\Modules\Quotes;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Actions\CancelQuote;
use App\Modules\Quotes\Actions\CreateQuote;
use App\Modules\Quotes\Actions\QuoteDraftData;
use App\Modules\Quotes\Actions\QuoteLineData;
use App\Modules\Quotes\Actions\UpdateQuote;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Pricing\PriceBasis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final readonly class QuoteController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateQuote $createQuote,
        private UpdateQuote $updateQuote,
        private CancelQuote $cancelQuote,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', QuoteStatus::Draft->value, QuoteStatus::Cancelled->value], true)) {
            $status = 'all';
        }

        $query = Quote::query()->with('account')->where('company_id', $this->companyId());
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->whereRaw('number ILIKE ?', [$like])
                    ->orWhereHas('account', fn ($account) => $account->whereRaw('legal_name ILIKE ?', [$like]));
            });
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('quotes.index', [
            'quotes' => $query->orderByDesc('quote_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'search' => $search,
            'statusFilter' => $status,
        ]);
    }

    public function create(): View
    {
        return $this->form(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $quote = $this->createQuote->handle(
            $this->draftData($validated),
            (string) ($validated['series_code'] ?? 'default'),
        );

        return redirect()->route('quotes.show', $quote->getKey())->with('status', 'Teklif oluşturuldu.');
    }

    public function show(int $quote): View
    {
        $quoteModel = $this->quote($quote)->load([
            'account',
            'lines.product',
            'lines.tax',
            'lines.taxZeroReason',
            'revisions',
        ]);

        return view('quotes.show', ['quote' => $quoteModel]);
    }

    public function edit(int $quote): View
    {
        $quoteModel = $this->quote($quote)->load('lines');
        if (! $quoteModel->isDraft()) {
            abort(409, 'Yalnız taslak teklifler düzenlenebilir.');
        }

        return $this->form($quoteModel);
    }

    public function update(Request $request, int $quote): RedirectResponse
    {
        $this->quote($quote);
        $validated = $request->validate($this->rules(includeSeries: false));
        $updated = $this->updateQuote->handle($quote, $this->draftData($validated));

        return redirect()->route('quotes.show', $updated->getKey())->with('status', 'Teklif güncellendi.');
    }

    public function cancel(int $quote): RedirectResponse
    {
        $this->quote($quote);
        $cancelled = $this->cancelQuote->handle($quote);

        return redirect()->route('quotes.show', $cancelled->getKey())->with('status', 'Teklif iptal edildi.');
    }

    private function form(?Quote $quote): View
    {
        $companyId = $this->companyId();

        return view('quotes.form', [
            'quote' => $quote,
            'accounts' => Account::query()
                ->where('company_id', $companyId)->where('status', 'active')
                ->whereIn('type', ['customer', 'mixed'])->orderBy('legal_name')->get(),
            'products' => Product::query()->with('tax')
                ->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
            'zeroReasons' => TaxZeroReason::query()
                ->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
            'priceBases' => PriceBasis::cases(),
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(bool $includeSeries = true): array
    {
        $rules = [
            'account_id' => ['required', 'integer'],
            'quote_date' => ['required', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:quote_date'],
            'currency_code' => ['required', 'string', 'size:3'],
            'document_discount_rate' => ['required', 'decimal:0,6', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.quantity' => ['required', 'decimal:0,6'],
            'lines.*.unit_price' => ['required', 'decimal:0,6'],
            'lines.*.price_basis' => ['required', Rule::enum(PriceBasis::class)],
            'lines.*.line_discount_rate' => ['required', 'decimal:0,6', 'min:0', 'max:100'],
            'lines.*.tax_zero_reason_id' => ['nullable', 'integer'],
        ];
        if ($includeSeries) {
            $rules['series_code'] = ['nullable', 'string', 'max:64'];
        }

        return $rules;
    }

    /** @param array<string, mixed> $validated */
    private function draftData(array $validated): QuoteDraftData
    {
        $lines = [];
        foreach ($validated['lines'] as $line) {
            $lines[] = new QuoteLineData(
                productId: (int) $line['product_id'],
                quantity: (string) $line['quantity'],
                unitPrice: (string) $line['unit_price'],
                priceBasis: PriceBasis::from((string) $line['price_basis']),
                lineDiscountRate: (string) $line['line_discount_rate'],
                taxZeroReasonId: isset($line['tax_zero_reason_id']) && $line['tax_zero_reason_id'] !== '' ? (int) $line['tax_zero_reason_id'] : null,
                description: isset($line['description']) ? (string) $line['description'] : null,
            );
        }

        return new QuoteDraftData(
            accountId: (int) $validated['account_id'],
            quoteDate: (string) $validated['quote_date'],
            validUntil: isset($validated['valid_until']) && $validated['valid_until'] !== '' ? (string) $validated['valid_until'] : null,
            currencyCode: (string) $validated['currency_code'],
            documentDiscountRate: (string) $validated['document_discount_rate'],
            note: isset($validated['note']) ? (string) $validated['note'] : null,
            lines: $lines,
        );
    }

    private function quote(int $quoteId): Quote
    {
        return Quote::query()->where('company_id', $this->companyId())->whereKey($quoteId)->firstOrFail();
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
