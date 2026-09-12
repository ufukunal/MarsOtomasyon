<?php

namespace App\Modules\UpdateCenter;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class UpdateCenterController
{
    public function __construct(
        private readonly UpdateCenterService $service,
        private readonly UpdateRunStore $runs,
    ) {}

    public function index(): View
    {
        return view('operations.update-center', [
            'updateStatus' => $this->service->status(),
            'checkResult' => session('update_check_result'),
            'checkError' => session('update_check_error'),
            'updateRuns' => $this->runs->latest(),
            'activeRun' => $this->runs->active(),
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    public function check(): RedirectResponse
    {
        try {
            $result = $this->service->check();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('operations.updates.index')
                ->with('update_check_error', 'Güncelleme manifesti güvenli biçimde doğrulanamadı. Yapılandırmayı ve release kaynağını kontrol edin.');
        }

        return redirect()
            ->route('operations.updates.index')
            ->with('update_check_result', $result)
            ->with('status', 'Güncelleme manifesti güven zincirinden geçti.');
    }

    public function prepare(Request $request): RedirectResponse
    {
        $validated = $request->validate(['request_key' => ['required', 'uuid']]);

        try {
            $manifest = $this->service->check();
            if (! $manifest['update_available'] || $manifest['same_version'] || $manifest['downgrade']) {
                throw new DomainException('Target version must be newer than the installed version.');
            }
            if (! $manifest['compatible']) {
                throw new DomainException('Target version is not compatible with this Mars/PHP runtime.');
            }

            $run = $this->runs->request(
                (string) $manifest['version'],
                (string) $manifest['channel'],
                (string) $manifest['manifest_sha256'],
                (string) $manifest['package_sha256'],
                $request->user()?->id,
                (string) $validated['request_key'],
                [
                    'package_url' => (string) $manifest['package_url'],
                    'released_at' => (string) $manifest['released_at'],
                    'release_notes_url' => $manifest['release_notes_url'],
                    'min_php' => (string) $manifest['min_php'],
                    'min_app_version' => $manifest['min_app_version'],
                    'prepared_at' => now()->toIso8601String(),
                ],
            );

            if ((string) $run->status === UpdateRunState::Requested->value) {
                $run = $this->runs->transition((int) $run->id, UpdateRunState::Verified, [
                    'verified_at' => now()->toIso8601String(),
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('update_check_error', 'Güncelleme hazırlığı güvenli biçimde tamamlanamadı. Manifest, sürüm uyumu veya aktif update durumu uygun değil.');
        }

        return back()->with('status', 'Güncelleme talebi doğrulandı. Host executor paketi güvenli staging alanına alabilir.');
    }

    public function apply(Request $request, int $run): RedirectResponse
    {
        try {
            $current = $this->runs->find($run);
            if ((string) $current->status !== UpdateRunState::Staged->value) {
                throw new DomainException('Only a staged update can be applied.');
            }

            $readiness = $this->service->readiness();
            if (! $readiness['apply_ready']) {
                throw new DomainException('System or backup readiness blocks update apply.');
            }

            $this->runs->annotate($run, [
                'apply_requested_at' => now()->toIso8601String(),
                'apply_requested_by_user_id' => $request->user()?->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('update_check_error', 'Güncelleme uygulanamadı: staging, sistem readiness veya backup readiness şartı sağlanmıyor.');
        }

        return back()->with('status', 'Apply talebi kaydedildi. Trusted host executor bu runı devralacak.');
    }

    public function rollback(Request $request, int $run): RedirectResponse
    {
        try {
            $current = $this->runs->find($run);
            $state = UpdateRunState::from((string) $current->status);
            if (! in_array($state, [UpdateRunState::Applying, UpdateRunState::HealthCheck], true)) {
                throw new DomainException('Rollback can only be requested while applying or health checking.');
            }

            $this->runs->transition($run, UpdateRunState::RollbackRequested, [
                'rollback_requested_at' => now()->toIso8601String(),
                'rollback_requested_by_user_id' => $request->user()?->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('update_check_error', 'Rollback talebi mevcut lifecycle durumunda kabul edilmedi.');
        }

        return back()->with('status', 'Rollback talebi kaydedildi. Trusted host executor önceki release ve safety backup yoluna dönecek.');
    }
}
