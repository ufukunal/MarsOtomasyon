<?php

use App\Http\Controllers\ReportExportDownloadController;
use App\Models\ReportExportJob;
use App\Models\User;
use App\Support\Reporting\ReportRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2EXPAUTH');
    $this->v2ReportCompany = $company;
    $this->v2ReportPeriod = $period;
    $this->v2ReportUser = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($this->v2ReportUser, $company, $period);
});

function v2ReportExport(int $companyId, int $userId, int $periodId): ReportExportJob
{
    return ReportExportJob::query()->create([
        'company_id' => $companyId,
        'user_id' => $userId,
        'report_key' => 'sales.invoices',
        'format' => 'csv',
        'filters' => [],
        'periods' => [$periodId],
        'permission_scope' => ['cost_view_required' => false],
        'parameters_hash' => str_repeat('a', 64),
        'status' => 'done',
        'storage_disk' => 'local',
        'storage_path' => 'v2-unit-not-available.csv',
    ]);
}

it('v2 report export refuses download to any user except the request owner', function () {
    $other = User::factory()->create();
    $export = v2ReportExport($this->v2ReportCompany->id, $other->id, $this->v2ReportPeriod->id);
    expect(fn () => app(ReportExportDownloadController::class)->__invoke($export, app(ReportRegistry::class)))
        ->toThrow(AuthorizationException::class);
});

it('v2 report export denies a revoked period membership before file retrieval', function () {
    $export = v2ReportExport($this->v2ReportCompany->id, $this->v2ReportUser->id, $this->v2ReportPeriod->id);
    DB::connection('master')->table('period_user_access')
        ->where('user_id', $this->v2ReportUser->id)
        ->where('period_id', $this->v2ReportPeriod->id)
        ->update(['is_active' => false]);
    expect(fn () => app(ReportExportDownloadController::class)->__invoke($export, app(ReportRegistry::class)))
        ->toThrow(AuthorizationException::class);
});

it('v2 report export denies file linked to another company even if same actor id', function () {
    [$otherCompany, $otherPeriod] = $this->createCompanyWithPeriod('V2EXPOTHER');
    $export = v2ReportExport($otherCompany->id, $this->v2ReportUser->id, $otherPeriod->id);
    \App\Support\Period\PeriodContext::useSystem($this->v2ReportCompany->id, $this->v2ReportPeriod->id);
    expect(fn () => app(ReportExportDownloadController::class)->__invoke($export, app(ReportRegistry::class)))
        ->toThrow(AuthorizationException::class);
});