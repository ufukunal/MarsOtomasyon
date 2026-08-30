<?php

namespace App\Modules\Instruments;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Instruments\Actions\InstrumentOperations;
use App\Modules\Instruments\Files\InstrumentFileManager;
use App\Modules\Instruments\Models\Instrument;
use App\Modules\Treasury\Models\TreasuryAccount;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class InstrumentController
{
    public function __construct(private ActiveCompanyContext $companyContext, private InstrumentOperations $operations, private InstrumentFileManager $files) {}

    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $direction = trim((string) $request->query('direction', ''));
        $kind = trim((string) $request->query('kind', ''));
        $query = Instrument::query()->where('company_id', $this->companyId())->with(['account', 'currentHolderAccount', 'currentTreasuryAccount']);
        if ($status !== '') $query->where('status', $status);
        if (in_array($direction, ['received', 'issued'], true)) $query->where('direction', $direction);
        if (in_array($kind, ['cheque', 'promissory_note'], true)) $query->where('kind', $kind);
        return view('instruments.index', [
            'instruments' => $query->orderBy('due_date')->orderBy('id')->paginate(50)->withQueryString(),
            'commercialAccounts' => $this->commercialAccounts(),
            'statusFilter' => $status, 'directionFilter' => $direction, 'kindFilter' => $kind,
        ]);
    }

    public function show(int $instrument): View
    {
        $model = $this->instrument($instrument);
        $model->load(['account', 'currentHolderAccount', 'currentTreasuryAccount', 'endorsedToAccount', 'events']);
        return view('instruments.show', [
            'instrument' => $model,
            'attachments' => $this->files->all($instrument),
            'banks' => TreasuryAccount::query()->where('company_id', $this->companyId())->where('type', 'bank')->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Account::query()->where('company_id', $this->companyId())->where('status', 'active')->whereIn('type', ['supplier', 'mixed'])->orderBy('legal_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'min:1'], 'direction' => ['required', Rule::in(['received', 'issued'])],
            'kind' => ['required', Rule::in(['cheque', 'promissory_note'])], 'document_no' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'decimal:0,6', 'gt:0'], 'currency_code' => ['required', 'string', 'size:3'],
            'issue_date' => ['nullable', 'date_format:Y-m-d'], 'delivery_date' => ['required', 'date_format:Y-m-d'], 'due_date' => ['required', 'date_format:Y-m-d'],
            'bank_name' => ['nullable', 'string', 'max:160'], 'branch_name' => ['nullable', 'string', 'max:120'],
            'drawer_or_maker' => ['nullable', 'string', 'max:200'], 'note' => ['nullable', 'string', 'max:5000'],
        ]);
        return $this->perform(fn (): Instrument => $this->operations->register(
            $this->companyId(), (int) $validated['account_id'], (string) $validated['direction'], (string) $validated['kind'],
            (string) $validated['document_no'], (string) $validated['amount'], (string) $validated['currency_code'],
            (string) $validated['delivery_date'], (string) $validated['due_date'], $this->nullableString($validated['issue_date'] ?? null),
            $this->nullableString($validated['bank_name'] ?? null), $this->nullableString($validated['branch_name'] ?? null),
            $this->nullableString($validated['drawer_or_maker'] ?? null), $this->nullableString($validated['note'] ?? null),
        ), 'Çek/senet kaydı oluşturuldu.');
    }

    public function sendToBank(Request $request, int $instrument): RedirectResponse
    {
        $data = $request->validate(['treasury_account_id' => ['required', 'integer', 'min:1'], 'event_date' => ['required', 'date_format:Y-m-d']]);
        return $this->perform(fn (): Instrument => $this->operations->sendToBank($this->companyId(), $instrument, (int) $data['treasury_account_id'], (string) $data['event_date']), 'Çek/senet bankaya gönderildi.');
    }

    public function recallFromBank(Request $request, int $instrument): RedirectResponse
    {
        $data = $request->validate(['event_date' => ['required', 'date_format:Y-m-d']]);
        return $this->perform(fn (): Instrument => $this->operations->recallFromBank($this->companyId(), $instrument, (string) $data['event_date']), 'Çek/senet bankadan portföye geri alındı.');
    }

    public function endorse(Request $request, int $instrument): RedirectResponse
    {
        $data = $request->validate(['supplier_account_id' => ['required', 'integer', 'min:1'], 'event_date' => ['required', 'date_format:Y-m-d']]);
        return $this->perform(fn (): Instrument => $this->operations->endorse($this->companyId(), $instrument, (int) $data['supplier_account_id'], (string) $data['event_date']), 'Çek/senet tedarikçiye ciro edildi.');
    }

    public function settle(Request $request, int $instrument): RedirectResponse
    {
        $data = $request->validate(['treasury_account_id' => ['required', 'integer', 'min:1'], 'event_date' => ['required', 'date_format:Y-m-d']]);
        return $this->perform(fn (): Instrument => $this->operations->settle($this->companyId(), $instrument, (int) $data['treasury_account_id'], (string) $data['event_date']), 'Çek/senet banka kapanışı işlendi.');
    }

    public function dishonor(Request $request, int $instrument): RedirectResponse
    {
        $data = $request->validate(['event_date' => ['required', 'date_format:Y-m-d']]);
        return $this->perform(fn (): Instrument => $this->operations->dishonor($this->companyId(), $instrument, (string) $data['event_date']), 'Çek/senet karşılıksız/ödenmedi olarak ters kayda alındı.');
    }

    public function returnToCounterparty(Request $request, int $instrument): RedirectResponse
    {
        $data = $request->validate(['event_date' => ['required', 'date_format:Y-m-d']]);
        return $this->perform(fn (): Instrument => $this->operations->returnToCounterparty($this->companyId(), $instrument, (string) $data['event_date']), 'Çek/senet karşı tarafa iade edildi.');
    }

    public function cancel(Request $request, int $instrument): RedirectResponse
    {
        $data = $request->validate(['event_date' => ['required', 'date_format:Y-m-d']]);
        return $this->perform(fn (): Instrument => $this->operations->cancel($this->companyId(), $instrument, (string) $data['event_date']), 'Çek/senet ters kayıtla iptal edildi.');
    }

    public function upload(Request $request, int $instrument): RedirectResponse
    {
        $data = $request->validate(['side' => ['required', Rule::in(['front', 'back'])], 'file' => ['required', 'file', 'max:51200']]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) throw ValidationException::withMessages(['file' => 'Dosya yüklenemedi.']);
        $this->files->upload($instrument, $upload, (string) $data['side']);
        return redirect()->route('instruments.show', $instrument)->with('status', 'Çek/senet görseli kaydedildi.');
    }

    public function download(int $instrument, int $attachment): StreamedResponse { return $this->files->download($instrument, $attachment); }

    public function detach(int $instrument, int $attachment): RedirectResponse
    {
        $this->files->detach($instrument, $attachment);
        return redirect()->route('instruments.show', $instrument)->with('status', 'Çek/senet görseli arşivlendi.');
    }

    /** @param callable():Instrument $operation */
    private function perform(callable $operation, string $message): RedirectResponse
    {
        try { $instrument = $operation(); }
        catch (DomainException|InvalidArgumentException $exception) { throw ValidationException::withMessages(['instrument' => $exception->getMessage()]); }
        return redirect()->route('instruments.show', $instrument->getKey())->with('status', $message);
    }

    private function instrument(int $id): Instrument { return Instrument::query()->where('company_id', $this->companyId())->findOrFail($id); }
    /** @return Collection<int, Account> */
    private function commercialAccounts(): Collection { return Account::query()->where('company_id', $this->companyId())->where('status', 'active')->whereIn('type', ['customer', 'supplier', 'mixed'])->orderBy('legal_name')->get(); }
    private function nullableString(mixed $value): ?string { return $value === null || $value === '' ? null : (string) $value; }
    private function companyId(): int { $id = $this->companyContext->requireCompany()->getKey(); return is_int($id) ? $id : throw new LogicException('Instrument operation requires a persisted active company.'); }
}
