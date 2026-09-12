<?php

namespace App\Modules\UpdateCenter;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class UpdateCenterController
{
    public function __construct(
        private readonly UpdateCenterService $service,
        private readonly UpdatePackageStager $stager,
        private readonly UpdateRunStore $runs,
    ) {}

    public function index(): View
    {
        return view('operations.update-center', [
            'updateStatus' => $this->service->status(),
            'checkResult' => session('update_check_result'),
            'checkError' => session('update_check_error'),
            'updateRuns' => $this->runs->latest(),
        ]);
    }

    public function check(): RedirectResponse
    {
        try {
            $result = $this->service->check();
        } catch (Throwable $exception) {
            report($exception);

            return $this->error('Güncelleme manifesti güvenli biçimde doğrulanamadı. Yapılandırmayı ve release kaynağını kontrol edin.');
        }

        return redirect()
            ->route('operations.updates.index')
            ->with('update_check_result', $result);
    }

    public function stage(Request $request): RedirectResponse
    {
        try {
            $release = $this->service->check();
            $userId = $request->user()?->getAuthIdentifier();
            $runId = $this->stager->stage($release, is_numeric($userId) ? (int) $userId : null);
        } catch (Throwable $exception) {
            report($exception);

            return $this->error('Güncelleme paketi güvenli staging alanına alınamadı. Paket indirilmedi veya doğrulama başarısız oldu.');
        }

        return redirect()
            ->route('operations.updates.index')
            ->with('status', "Güncelleme paketi doğrulandı ve staging tamamlandı. Run #{$runId}.");
    }

    public function apply(int $run): RedirectResponse
    {
        if (! (bool) config('update-center.agent_enabled', false)) {
            return $this->error('Deploy agent etkin değil. Host-side agent kurulmadan aktivasyon kuyruğa alınamaz.');
        }

        try {
            $this->runs->transition($run, UpdateRunState::ApplyRequested, [
                'apply_requested_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->error('Güncelleme aktivasyon kuyruğuna alınamadı. Run durumunu kontrol edin.');
        }

        return redirect()
            ->route('operations.updates.index')
            ->with('status', "Run #{$run} deploy agent kuyruğuna alındı.");
    }

    public function rollback(int $run): RedirectResponse
    {
        if (! (bool) config('update-center.agent_enabled', false)) {
            return $this->error('Deploy agent etkin değil. Rollback kuyruğa alınamaz.');
        }

        try {
            $this->runs->transition($run, UpdateRunState::RollbackRequested, [
                'rollback_requested_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->error('Rollback isteği oluşturulamadı. Run durumu veya rollback kanıtını kontrol edin.');
        }

        return redirect()
            ->route('operations.updates.index')
            ->with('status', "Run #{$run} rollback kuyruğuna alındı.");
    }

    private function error(string $message): RedirectResponse
    {
        return redirect()
            ->route('operations.updates.index')
            ->with('update_check_error', $message);
    }
}
