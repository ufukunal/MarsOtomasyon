<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\TaxZeroReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class TaxSettingsController
{
    public function __construct(private readonly ActiveCompanyContext $companyContext) {}

    public function index(): View
    {
        $companyId = $this->companyId();

        return view('settings.taxes.index', [
            'taxes' => Tax::query()->where('company_id', $companyId)->orderBy('rate')->orderBy('code')->get(),
            'zeroReasons' => TaxZeroReason::query()->where('company_id', $companyId)->orderBy('code')->get(),
        ]);
    }

    public function createTax(): View
    {
        return view('settings.taxes.tax-form', ['tax' => null]);
    }

    public function storeTax(Request $request): RedirectResponse
    {
        $data = $this->validateTax($request);
        $code = mb_strtoupper(trim((string) $data['code']));
        $this->assertTaxCodeAvailable($code);

        $tax = Tax::query()->create([
            'company_id' => $this->companyId(),
            'code' => $code,
            'name' => trim((string) $data['name']),
            'rate' => (string) $data['rate'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('settings.taxes.show', $tax)->with('status', 'Vergi tanımı oluşturuldu.');
    }

    public function showTax(int $tax): View
    {
        return view('settings.taxes.tax-show', ['tax' => $this->tax($tax)]);
    }

    public function editTax(int $tax): View
    {
        return view('settings.taxes.tax-form', ['tax' => $this->tax($tax)]);
    }

    public function updateTax(Request $request, int $tax): RedirectResponse
    {
        $tax = $this->tax($tax);
        $data = $this->validateTax($request);
        $code = mb_strtoupper(trim((string) $data['code']));
        $this->assertTaxCodeAvailable($code, (int) $tax->getKey());

        $tax->update([
            'code' => $code,
            'name' => trim((string) $data['name']),
            'rate' => (string) $data['rate'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('settings.taxes.show', $tax)->with('status', 'Vergi tanımı güncellendi.');
    }

    public function createZeroReason(): View
    {
        return view('settings.taxes.zero-reason-form', ['zeroReason' => null]);
    }

    public function storeZeroReason(Request $request): RedirectResponse
    {
        $data = $this->validateZeroReason($request);
        $code = mb_strtoupper(trim((string) $data['code']));
        $this->assertZeroReasonCodeAvailable($code);

        $reason = TaxZeroReason::query()->create([
            'company_id' => $this->companyId(),
            'code' => $code,
            'name' => trim((string) $data['name']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('settings.tax-zero-reasons.show', $reason)->with('status', 'KDV sıfır nedeni oluşturuldu.');
    }

    public function showZeroReason(int $zeroReason): View
    {
        return view('settings.taxes.zero-reason-show', ['zeroReason' => $this->zeroReason($zeroReason)]);
    }

    public function editZeroReason(int $zeroReason): View
    {
        return view('settings.taxes.zero-reason-form', ['zeroReason' => $this->zeroReason($zeroReason)]);
    }

    public function updateZeroReason(Request $request, int $zeroReason): RedirectResponse
    {
        $reason = $this->zeroReason($zeroReason);
        $data = $this->validateZeroReason($request);
        $code = mb_strtoupper(trim((string) $data['code']));
        $this->assertZeroReasonCodeAvailable($code, (int) $reason->getKey());

        $reason->update([
            'code' => $code,
            'name' => trim((string) $data['name']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('settings.tax-zero-reasons.show', $reason)->with('status', 'KDV sıfır nedeni güncellendi.');
    }

    /** @return array{code:string,name:string,rate:string} */
    private function validateTax(Request $request): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'rate' => ['required', 'string', 'regex:/^\d{1,3}(\.\d{1,6})?$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rate = (string) $validated['rate'];
        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');

        if ((int) $whole > 100 || ((int) $whole === 100 && trim($fraction, '0') !== '')) {
            throw ValidationException::withMessages(['rate' => 'Vergi oranı 0 ile 100 arasında olmalıdır.']);
        }

        return [
            'code' => (string) $validated['code'],
            'name' => (string) $validated['name'],
            'rate' => $rate,
        ];
    }

    /** @return array{code:string,name:string} */
    private function validateZeroReason(Request $request): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ['code' => (string) $validated['code'], 'name' => (string) $validated['name']];
    }

    private function tax(int $id): Tax
    {
        return Tax::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function zeroReason(int $id): TaxZeroReason
    {
        return TaxZeroReason::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function assertTaxCodeAvailable(string $code, ?int $exceptId = null): void
    {
        $query = Tax::query()->where('company_id', $this->companyId())->whereRaw('lower(code) = ?', [mb_strtolower($code)]);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => 'Bu vergi kodu zaten kullanılıyor.']);
        }
    }

    private function assertZeroReasonCodeAvailable(string $code, ?int $exceptId = null): void
    {
        $query = TaxZeroReason::query()->where('company_id', $this->companyId())->whereRaw('lower(code) = ?', [mb_strtolower($code)]);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['code' => 'Bu KDV sıfır nedeni kodu zaten kullanılıyor.']);
        }
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
