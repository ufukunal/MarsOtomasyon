<?php

namespace App\Livewire\Shell;

use App\Livewire\Concerns\WithIdempotentMutations;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CompanySwitcher extends Component
{
    use WithIdempotentMutations;

    public ?int $companyId = null;

    public function mount(): void
    {
        $this->seedMutationKeys(['selectCompany']);
        $this->companyId = session('active_company_id')
            ?? Auth::user()?->last_company_id;
    }

    public function updatedCompanyId(?int $companyId): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $allowed = $user->companies()
            ->whereKey($companyId)
            ->exists();

        abort_unless($allowed, 403);

        $this->runMasterMutation('selectCompany', function () use ($user, $companyId): bool {
            $user->forceFill([
                'last_company_id' => $companyId,
                'last_period_id' => null,
            ])->save();

            return true;
        });

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
