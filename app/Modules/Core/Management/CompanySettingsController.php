<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Company\ActiveCompanyContext;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CompanySettingsController
{
    public function __construct(private readonly ActiveCompanyContext $companyContext) {}

    public function show(): View
    {
        return view('settings.company.show', [
            'company' => $this->companyContext->requireCompany(),
        ]);
    }

    public function edit(): View
    {
        return view('settings.company.edit', [
            'company' => $this->companyContext->requireCompany(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'base_currency_code' => mb_strtoupper(trim((string) $request->input('base_currency_code'))),
        ]);

        $validated = $request->validate([
            'base_currency_code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::exists('currencies', 'code')->where('is_active', true),
            ],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ]);

        $company = $this->companyContext->requireCompany();
        $company->base_currency_code = (string) $validated['base_currency_code'];
        $company->timezone = (string) $validated['timezone'];
        $company->save();

        return redirect()->route('settings.company.show')
            ->with('status', 'Firma ve sistem ayarları güncellendi.');
    }
}
