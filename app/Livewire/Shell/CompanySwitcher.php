<?php

namespace App\Livewire\Shell;

use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CompanySwitcher extends Component
{
    public ?int $companyId = null;

    public function mount(): void
    {
        $this->companyId = session('active_company_id')
            ?? Auth::user()?->last_company_id;
    }

    public function updatedCompanyId(?int $companyId): void
    {
        abort_unless(Auth::check(), 403);

        $user = Auth::user();

        $allowed = $user->companies()
            ->whereKey($companyId)
            ->exists();

        abort_unless($allowed, 403);

        $user->forceFill([
            'last_company_id' => $companyId,
            'last_period_id' => null,
        ])->save();

        session([
            'active_company_id' => $companyId,
        ]);

        session()->forget([
            'active_period_id',
            'active_year',
        ]);

        $this->redirect('/secim', navigate: false);
    }

    public function render(): View
    {
        $companies = Auth::check()
            ? Auth::user()->companies()->where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('livewire.shell.company-switcher', [
            'companies' => $companies,
        ]);
    }
}
