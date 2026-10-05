<?php

namespace App\Livewire\Reporting;

use App\Support\Period\PeriodContext;
use App\Support\Reporting\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class Dashboard extends Component
{
    public function mount(): void
    {
        PeriodContext::ensure();
    }

    public function render(): View
    {
        return view('livewire.reporting.dashboard', [
            'widgets' => app(DashboardService::class)->widgets(),
        ])->layout('layouts.app', [
            'pageTitle' => 'Dashboard',
            'pageDescription' => 'Yetkinize açık operasyonel raporların güncel özeti.',
        ]);
    }
}
