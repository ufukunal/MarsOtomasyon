<?php

namespace App\Livewire\Pages\Auth;

use App\Models\Period;
use App\Support\Audit\AuditContext;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PeriodSelection extends Component
{
    public ?int $companyId = null;

    public ?int $periodId = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->companyId = $user?->last_company_id;
        $this->periodId = $user?->last_period_id;
    }

    public function updatedCompanyId(): void
    {
        $this->periodId = null;
    }

    public function select(): void
    {
        $data = $this->validate([
            'companyId' => ['required', 'integer'],
            'periodId' => ['required', 'integer'],
        ]);

        $user = auth()->user();

        abort_unless($user->companies()->whereKey($data['companyId'])->exists(), 403);

        $period = $user->accessiblePeriods()
            ->where('periods.id', $data['periodId'])
            ->where('periods.company_id', $data['companyId'])
            ->wherePivot('is_active', true)
            ->firstOrFail();

        abort_if($period->status === 'archived', 403);

        PeriodContext::use((int) $data['companyId'], (int) $data['periodId']);

        $user->forceFill([
            'last_company_id' => $data['companyId'],
            'last_period_id' => $data['periodId'],
        ])->save();

        AuditContext::master(
            'Şirket/dönem seçildi.',
            [
                'company_id' => $data['companyId'],
                'period_id' => $data['periodId'],
                'database_name' => $period->database_name,
            ],
            $period,
            'period_selected',
        );

        if ($period->status === 'closed') {
            session()->flash('warning', "{$period->year} dönemi kapalı ve salt okunurdur.");
        }

        $this->redirect('/', navigate: false);
    }

    public function render(): View
    {
        $user = auth()->user();

        $companies = $user->companies()
            ->where('companies.is_active', true)
            ->orderBy('companies.name')
            ->get();

        $periods = $this->companyId
            ? $user->accessiblePeriods()
                ->where('periods.company_id', $this->companyId)
                ->wherePivot('is_active', true)
                ->orderByDesc('periods.year')
                ->get()
            : collect();

        return view('livewire.pages.auth.period-selection', compact('companies', 'periods'))
            ->layout('layouts.guest', ['title' => 'Şirket ve Dönem Seçimi']);
    }
}
