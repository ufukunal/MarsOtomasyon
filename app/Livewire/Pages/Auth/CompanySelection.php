<?php

namespace App\Livewire\Pages\Auth;

use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\User;
use App\Support\Company\CompanyDatabaseContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * DOC2-only selection screen. No year, period ID or period database handling.
 */
class CompanySelection extends Component
{
    use WithIdempotentMutations;

    public ?int $companyId = null;

    public function mount(): void
    {
        abort_unless(config('mars_doc2.mode') === 'doc2', 404);
        $this->seedMutationKeys(['select']);
        $this->companyId = Auth::user()?->last_company_id;
    }

    public function select(): void
    {
        $data = $this->validate([
            'companyId' => ['required', 'integer', 'min:1'],
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User && $user->is_active, 403);

        // Authorization checks Master company_user and verified DOC2 registry.
        CompanyDatabaseContext::activateForUser((int) $data['companyId'], (int) $user->getAuthIdentifier());

        try {
            $this->runMasterMutation('select', function () use ($data, $user): bool {
                $user->forceFill([
                    'last_company_id' => (int) $data['companyId'],
                    'last_period_id' => null,
                ])->save();

                return true;
            });

            session([
                'active_company_id' => (int) $data['companyId'],
            ]);
            session()->forget(['active_period_id', 'active_year']);
        } finally {
            CompanyDatabaseContext::release();
        }

        $this->redirect('/', navigate: false);
    }

    public function render(): View
    {
        $user = Auth::user();

        $companies = $user
            ? $user->companies()->where('companies.is_active', true)->orderBy('companies.name')->get()
            : collect();

        return view('livewire.pages.auth.company-selection', compact('companies'))
            ->layout('layouts.guest', ['title' => 'Şirket Seçimi']);
    }
}
