<?php

namespace App\Livewire\Pages\Settings;

use App\Actions\Periods\ClosePeriod;
use App\Actions\Periods\CreatePeriod;
use App\Actions\Periods\ReopenPeriod;
use App\Models\Company;
use App\Models\Period;
use App\Livewire\Concerns\WithIdempotentMutations;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Periods extends Component
{
    use WithIdempotentMutations;

    public ?int $companyId = null;

    public int $year;

    public string $reopenReason = '';

    public function mount(): void
    {
        $this->seedMutationKeys(['createPeriod','close','reopen']);
        Gate::authorize('periods.view');

        $this->companyId = auth()->user()?->last_company_id;
        $this->year = now()->year + 1;
    }

    public function createPeriod(CreatePeriod $action): void
    {
        Gate::authorize('periods.create');

        $validated = $this->validate([
            'companyId' => ['required', 'integer', 'exists:master.companies,id'],
            'year' => ['required', 'integer', 'between:2000,2200'],
        ]);

        $company = Company::query()->findOrFail($validated['companyId']);
        $this->runMasterMutation('createPeriod', fn () => $action->handle($company, (int) $validated['year']));

        session()->flash(
            'warning',
            'Yeni dönem oluşturuldu. Erişim otomatik verilmez; period_user_access ayrıca tanımlanmalıdır.',
        );
    }

    public function close(int $periodId, ClosePeriod $action): void
    {
        $this->runMasterMutation('close', fn () => $action->handle(Period::query()->findOrFail($periodId)));
    }

    public function reopen(int $periodId, ReopenPeriod $action): void
    {
        $this->runMasterMutation(
            'reopen',
            fn () => $action->handle(
                Period::query()->findOrFail($periodId),
                $this->reopenReason,
            ),
        );

        $this->reopenReason = '';
    }

    public function render(): View
    {
        $periods = Period::query()
            ->with('company')
            ->orderBy('company_id')
            ->orderByDesc('year')
            ->get();

        return view('livewire.pages.settings.periods', [
            'periods' => $periods,
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', [
            'pageTitle' => 'Dönemler',
        ]);
    }
}
