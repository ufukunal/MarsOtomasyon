<?php

namespace App\Livewire\Components;

use App\Models\Period;
use App\Support\Audit\AuditContext;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PeriodSwitcher extends Component
{
    public ?int $companyId = null;

    public ?int $periodId = null;

    public function mount(): void
    {
        $this->companyId = session('active_company_id') ?? Auth::user()?->last_company_id;
        $this->periodId = session('active_period_id') ?? Auth::user()?->last_period_id;
    }

    public function updatedCompanyId(?int $companyId): void
    {
        abort_unless(Auth::check(), 403);

        $allowed = Auth::user()->companies()->whereKey($companyId)->exists();
        abort_unless($allowed, 403);

        $this->periodId = null;

        Auth::user()->forceFill([
            'last_company_id' => $companyId,
            'last_period_id' => null,
        ])->save();

        session(['active_company_id' => $companyId]);
        session()->forget(['active_period_id', 'active_year']);
        PeriodContext::clear();
        session(['active_company_id' => $companyId]);
    }

    public function updatedPeriodId(?int $periodId): void
    {
        if (! $periodId || ! $this->companyId) {
            return;
        }

        $user = Auth::user();

        $period = $user->accessiblePeriods()
            ->where('periods.id', $periodId)
            ->where('periods.company_id', $this->companyId)
            ->wherePivot('is_active', true)
            ->firstOrFail();

        abort_if($period->status === 'archived', 403);

        PeriodContext::use((int) $this->companyId, (int) $periodId);

        $user->forceFill([
            'last_company_id' => $this->companyId,
            'last_period_id' => $periodId,
        ])->save();

        AuditContext::master(
            'Aktif dönem değiştirildi.',
            [
                'company_id' => $this->companyId,
                'period_id' => $periodId,
                'year' => $period->year,
            ],
            $period,
            'period_selected',
        );

        if ($period->status === 'closed') {
            session()->flash('warning', "{$period->year} dönemi kapalı ve salt okunurdur.");
        }

        $this->redirect(request()->header('Referer') ?: '/', navigate: false);
    }

    public function render(): View
    {
        $user = Auth::user();

        $companies = $user
            ? $user->companies()->where('is_active', true)->orderBy('name')->get()
            : collect();

        $periods = ($user && $this->companyId)
            ? $user->accessiblePeriods()
                ->where('periods.company_id', $this->companyId)
                ->wherePivot('is_active', true)
                ->where('periods.status', '!=', 'archived')
                ->orderByDesc('periods.year')
                ->get()
            : collect();

        return view('livewire.components.period-switcher', compact('companies', 'periods'));
    }
}
