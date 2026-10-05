<?php

namespace App\Livewire\Reporting;

use App\Models\ReportExportJob;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class ExportCenter extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
        PeriodContext::ensure();
    }

    public function render(): View
    {
        $jobs = ReportExportJob::query()
            ->where('company_id', PeriodContext::companyId())
            ->where('user_id', auth()->id())
            ->latest('id')
            ->limit(100)
            ->get();

        return view('livewire.reporting.export-center', [
            'jobs' => $jobs,
        ])->layout('layouts.app', [
            'pageTitle' => 'Export Merkezi',
            'pageDescription' => 'Queue ile üretilen rapor dosyaları ve üretim geçmişi.',
        ]);
    }
}
