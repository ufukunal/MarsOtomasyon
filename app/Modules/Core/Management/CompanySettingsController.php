<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Models\Company;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CompanySettingsController
{
    public function __construct(
        private readonly ActiveCompanyContext $companyContext,
        private readonly AuditRecorder $audit,
    ) {}

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
                'required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/',
                Rule::exists('currencies', 'code')->where('is_active', true),
            ],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ]);

        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        DB::transaction(function () use ($companyId, $validated): void {
            $company = Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($company);

            $company->base_currency_code = (string) $validated['base_currency_code'];
            $company->timezone = (string) $validated['timezone'];
            $company->save();

            $this->audit->record(
                AuditAction::CompanySettingsUpdated,
                AuditTargetType::Company,
                $company->getKey(),
                before: $before,
                after: $this->snapshot($company),
            );
        });

        return redirect()->route('settings.company.show')
            ->with('status', 'Firma ve sistem ayarları güncellendi.');
    }

    /** @return array{base_currency_code:string,timezone:string} */
    private function snapshot(Company $company): array
    {
        return [
            'base_currency_code' => (string) $company->base_currency_code,
            'timezone' => (string) $company->timezone,
        ];
    }
}
