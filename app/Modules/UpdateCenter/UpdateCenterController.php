<?php

namespace App\Modules\UpdateCenter;

use Illuminate\Http\RedirectResponse;
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
            ->with('update_check_result', $result);
    }
}
