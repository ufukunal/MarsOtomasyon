<?php

namespace App\Livewire\Reporting;

use App\Models\PrintJob;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class PrintHistory extends Component
{
    public string $status = '';
    public string $printType = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
        PeriodContext::ensure();
    }

    public function render(): View
    {
        $query = PrintJob::query()
            ->with(['template:id,template_key,revision_no,name', 'profile:id,printer_name'])
            ->where('company_id', PeriodContext::companyId())
            ->latest('id');

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->printType !== '') {
            $query->where('print_type', $this->printType);
        }

        return view('livewire.reporting.print-history', [
            'jobs' => $query->limit(200)->get(),
        ])->layout('layouts.app', [
            'pageTitle' => 'Yazdırma Geçmişi',
            'pageDescription' => 'Tekil ve toplu baskı provenance, profil, template revizyonu ve sonuç geçmişi.',
        ]);
    }
}
