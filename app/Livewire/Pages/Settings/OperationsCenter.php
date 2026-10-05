<?php

namespace App\Livewire\Pages\Settings;

use App\Jobs\RunRecoverySetBackupJob;
use App\Jobs\VerifyRecoverySetBackupJob;
use App\Models\BackupRun;
use App\Models\DeploymentRun;
use App\Models\HealthCheckRun;
use App\Models\RestoreRun;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class OperationsCenter extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('audit.view'), 403);
    }

    public function backupNow(): void
    {
        Gate::authorize('companies.update');

        RunRecoverySetBackupJob::dispatch('manual');
        session()->flash('status', 'Recovery-set backup queueya alındı.');
    }

    public function verifyBackup(int $backupRunId): void
    {
        Gate::authorize('companies.update');

        $backup = BackupRun::query()
            ->whereIn('status', ['done', 'verified'])
            ->findOrFail($backupRunId);

        VerifyRecoverySetBackupJob::dispatch((int) $backup->id);
        session()->flash('status', 'Temporary restore provası queueya alındı.');
    }

    public function render(): View
    {
        return view('livewire.pages.settings.operations-center', [
            'deployments' => DeploymentRun::query()->latest('id')->limit(50)->get(),
            'backups' => BackupRun::query()->latest('id')->limit(50)->get(),
            'restores' => RestoreRun::query()->latest('id')->limit(50)->get(),
            'healthRuns' => HealthCheckRun::query()->latest('id')->limit(50)->get(),
        ])->layout('layouts.app', [
            'pageTitle' => 'Operasyon Merkezi',
            'pageDescription' => 'Deployment, recovery-set backup/restore ve production health geçmişi.',
        ]);
    }
}
