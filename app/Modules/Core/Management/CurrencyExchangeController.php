<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CurrencyExchangeController
{
    public function __construct(
        private readonly ActiveCompanyContext $companyContext,
        private readonly AuditRecorder $audit,
    ) {}

    public function index(): View
    {
        return view('settings.exchange-rates.index', [
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
            'rates' => ExchangeRate::query()
                ->where('company_id', $this->companyId())
                ->orderByDesc('rate_date')
                ->orderBy('from_currency_code')
                ->orderBy('to_currency_code')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('settings.exchange-rates.form', [
            'rate' => null,
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, false);
        $this->assertIdentityAvailable($data['rate_date'], $data['from_currency_code'], $data['to_currency_code']);

        $rate = DB::transaction(function () use ($data): ExchangeRate {
            $rate = ExchangeRate::query()->create([
                'company_id' => $this->companyId(),
                ...$data,
            ]);

            $this->audit->record(
                AuditAction::ExchangeRateCreated,
                AuditTargetType::ExchangeRate,
                (int) $rate->getKey(),
                after: $this->snapshot($rate),
            );

            return $rate;
        });

        return redirect()->route('settings.exchange-rates.show', $rate)->with('status', 'Kur kaydı oluşturuldu.');
    }

    public function show(int $rate): View
    {
        return view('settings.exchange-rates.show', ['rate' => $this->rate($rate)]);
    }

    public function edit(int $rate): View
    {
        return view('settings.exchange-rates.form', [
            'rate' => $this->rate($rate),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, int $rate): RedirectResponse
    {
        $data = $this->validated($request, true);

        $rate = DB::transaction(function () use ($rate, $data): ExchangeRate {
            $locked = ExchangeRate::query()
                ->where('company_id', $this->companyId())
                ->lockForUpdate()
                ->findOrFail($rate);

            $before = $this->snapshot($locked);
            $locked->update([
                'rate' => $data['rate'],
                'source' => $data['source'],
            ]);

            $this->audit->record(
                AuditAction::ExchangeRateUpdated,
                AuditTargetType::ExchangeRate,
                (int) $locked->getKey(),
                before: $before,
                after: $this->snapshot($locked),
            );

            return $locked;
        });

        return redirect()->route('settings.exchange-rates.show', $rate)->with('status', 'Kur değeri güncellendi.');
    }

    /** @return array{rate_date:string,from_currency_code:string,to_currency_code:string,rate:string,source:string} */
    private function validated(Request $request, bool $updating): array
    {
        $validated = $request->validate([
            'rate_date' => [$updating ? 'nullable' : 'required', 'date_format:Y-m-d'],
            'from_currency_code' => [$updating ? 'nullable' : 'required', 'string', 'size:3'],
            'to_currency_code' => [$updating ? 'nullable' : 'required', 'string', 'size:3'],
            'rate' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,10})?$/'],
            'source' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $rateValue = (string) $validated['rate'];
        if (preg_match('/^0+(\.0+)?$/', $rateValue) === 1) {
            throw ValidationException::withMessages(['rate' => 'Kur sıfırdan büyük olmalıdır.']);
        }

        if ($updating) {
            return [
                'rate_date' => '',
                'from_currency_code' => '',
                'to_currency_code' => '',
                'rate' => $rateValue,
                'source' => mb_strtolower(trim((string) $validated['source'])),
            ];
        }

        $from = mb_strtoupper(trim((string) $validated['from_currency_code']));
        $to = mb_strtoupper(trim((string) $validated['to_currency_code']));

        if ($from === $to) {
            throw ValidationException::withMessages(['to_currency_code' => 'Kaynak ve hedef para birimi aynı olamaz.']);
        }

        $activeCurrencies = Currency::query()->where('is_active', true)->whereKey([$from, $to])->pluck('code')->all();
        if (count(array_unique($activeCurrencies)) !== 2) {
            throw ValidationException::withMessages(['from_currency_code' => 'Geçerli ve aktif para birimleri seçilmelidir.']);
        }

        return [
            'rate_date' => (string) $validated['rate_date'],
            'from_currency_code' => $from,
            'to_currency_code' => $to,
            'rate' => $rateValue,
            'source' => mb_strtolower(trim((string) $validated['source'])),
        ];
    }

    /** @return array{rate_date:string,from_currency_code:string,to_currency_code:string,rate:string,source:string} */
    private function snapshot(ExchangeRate $rate): array
    {
        return [
            'rate_date' => $rate->rate_date?->format('Y-m-d') ?? '',
            'from_currency_code' => (string) $rate->from_currency_code,
            'to_currency_code' => (string) $rate->to_currency_code,
            'rate' => (string) $rate->rate,
            'source' => (string) $rate->source,
        ];
    }

    private function assertIdentityAvailable(string $date, string $from, string $to): void
    {
        if (ExchangeRate::query()
            ->where('company_id', $this->companyId())
            ->where('rate_date', $date)
            ->where('from_currency_code', $from)
            ->where('to_currency_code', $to)
            ->exists()) {
            throw ValidationException::withMessages(['rate_date' => 'Bu tarih ve para birimi çifti için kur zaten kayıtlı.']);
        }
    }

    private function rate(int $id): ExchangeRate
    {
        return ExchangeRate::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
