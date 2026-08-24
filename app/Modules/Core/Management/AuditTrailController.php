<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\AuditEntry;
use Illuminate\View\View;

final class AuditTrailController
{
    public function __construct(private readonly ActiveCompanyContext $companyContext) {}

    public function index(): View
    {
        $company = $this->companyContext->requireCompany();

        return view('settings.audit.index', [
            'entries' => AuditEntry::query()
                ->where('company_id', $company->getKey())
                ->with('actorUser')
                ->orderByDesc('occurred_at')
                ->limit(200)
                ->get(),
            'timezone' => (string) $company->timezone,
        ]);
    }

    public function show(int $audit): View
    {
        $company = $this->companyContext->requireCompany();
        $entry = AuditEntry::query()
            ->where('company_id', $company->getKey())
            ->with('actorUser')
            ->findOrFail($audit);

        return view('settings.audit.show', [
            'entry' => $entry,
            'timezone' => (string) $company->timezone,
        ]);
    }
}
