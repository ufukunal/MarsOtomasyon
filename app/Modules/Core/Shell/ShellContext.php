<?php

namespace App\Modules\Core\Shell;

use App\Modules\Core\Branch\ActiveBranchContext;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\CompanyStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final readonly class ShellContext
{
    public function __construct(
        private AppNavigation $navigation,
        private ActiveCompanyContext $companyContext,
        private ActiveBranchContext $branchContext,
    ) {}

    /**
     * @return array{
     *   navigation:list<array{label:string,route:string}>,
     *   user:User|null,
     *   companies:Collection<int,CompanyMembership>,
     *   company:\App\Modules\Core\Models\Company|null,
     *   branches:Collection<int,Branch>,
     *   branch:Branch|null
     * }
     */
    public function state(Request $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            /** @var Collection<int, CompanyMembership> $emptyCompanies */
            $emptyCompanies = collect();
            /** @var Collection<int, Branch> $emptyBranches */
            $emptyBranches = collect();

            return [
                'navigation' => [],
                'user' => null,
                'companies' => $emptyCompanies,
                'company' => null,
                'branches' => $emptyBranches,
                'branch' => null,
            ];
        }

        /** @var Collection<int, CompanyMembership> $companies */
        $companies = $user->memberships()
            ->where('is_active', true)
            ->with('company')
            ->get()
            ->filter(
                fn (CompanyMembership $membership): bool => $membership->company?->status === CompanyStatus::Active,
            )
            ->values();

        $company = $this->companyContext->company();
        /** @var Collection<int, Branch> $branches */
        $branches = collect();
        if ($company !== null) {
            $branches = Branch::query()
                ->where('company_id', $company->getKey())
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        $branch = $this->branchContext->branch();
        if ($branch === null) {
            $sessionBranchId = $request->session()->get('active_branch_id');
            if (is_int($sessionBranchId) || (is_string($sessionBranchId) && ctype_digit($sessionBranchId))) {
                $branch = $branches->first(
                    fn (Branch $candidate): bool => (int) $candidate->getKey() === (int) $sessionBranchId,
                );
            }
        }

        return [
            'navigation' => $this->navigation->items(),
            'user' => $user,
            'companies' => $companies,
            'company' => $company,
            'branches' => $branches,
            'branch' => $branch,
        ];
    }
}
