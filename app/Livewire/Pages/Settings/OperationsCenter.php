<?php

namespace App\Livewire\Pages\Settings;

use App\Jobs\RunRecoverySetBackupJob;
use App\Jobs\VerifyRecoverySetBackupJob;
use App\Models\BackupRun;
use App\Models\Company;
use App\Support\Company\CompanyContext;
use App\Models\DeploymentRun;
use App\Models\HealthCheckRun;
use App\Models\RestoreRun;
use App\Support\Operations\OperationalHealthService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class OperationsCenter extends Component
{
    public function mount(): void
    {
        $this->assertGlobalOperationsAccess();
    }

    public function backupNow(): void
    {
        $this->assertGlobalOperationsAccess();

        RunRecoverySetBackupJob::dispatch('manual');
        session()->flash('status', 'Recovery-set backup queueya alındı.');
    }

    public function verifyBackup(int $backupRunId): void
    {
        $this->assertGlobalOperationsAccess();

        $backup = BackupRun::query()
            ->whereIn('status', ['done', 'verified'])
            ->findOrFail($backupRunId);

        VerifyRecoverySetBackupJob::dispatch((int) $backup->id);
        session()->flash('status', 'Temporary restore provası queueya alındı.');
    }

    public function runHealth(OperationalHealthService $health): void
    {
        $this->assertGlobalOperationsAccess();

        $health->check(true);
        session()->flash('status', 'Operational health kontrolü tamamlandı.');
    }

    public function render(): View
    {
        $this->assertGlobalOperationsAccess();

        $healthRuns = HealthCheckRun::query()->latest('id')->limit(50)->get();

        return view('livewire.pages.settings.operations-center', [
            'deployments' => DeploymentRun::query()->latest('id')->limit(50)->get(),
            'backups' => BackupRun::query()->latest('id')->limit(50)->get(),
            'restores' => RestoreRun::query()->latest('id')->limit(50)->get(),
            'healthRuns' => $healthRuns,
            'latestHealth' => $healthRuns->first(),
        ])->layout('layouts.app', [
            'pageTitle' => 'Operasyon Merkezi',
            'pageDescription' => 'Deployment, recovery-set backup/restore ve production health geçmişi.',
        ]);
    }

    private function assertGlobalOperationsAccess(): void
    {
        $user = auth()->user();

        abort_unless($user?->is_active, 403);
        Gate::forUser($user)->authorize('companies.update');

        $companyIds = Company::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($companyIds === []) {
            return;
        }

        $accessible = DB::connection('master')
            ->table('company_user')
            ->where('user_id', $user->id)
            ->whereIn('company_id', $companyIds)
            ->pluck('company_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        abort_unless(array_diff($companyIds, $accessible) === [], 403);

        $previousCompanyId = CompanyContext::id();

        try {
            foreach ($companyIds as $companyId) {
                CompanyContext::use($companyId);
                Gate::forUser($user)->authorize('companies.update');
            }
        } finally {
            if ($previousCompanyId !== null) {
                CompanyContext::use($previousCompanyId);
            } else {
                CompanyContext::clear();
            }
        }
    }

}
