<?php

namespace App\Modules\Core\Shell;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\View\View;

final readonly class WorkspaceController
{
    public function __construct(private ActiveCompanyContext $companyContext) {}

    public function index(Request $request): View
    {
        $company = $this->companyContext->requireCompany();
        $companyId = $company->getKey();
        abort_unless(is_int($companyId), 409, 'Aktif şirket geçerli değil.');

        $activeBranches = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $selectedBranchId = $request->session()->get('active_branch_id');
        $selectedBranch = null;
        if (is_int($selectedBranchId) || (is_string($selectedBranchId) && ctype_digit($selectedBranchId))) {
            $selectedBranch = $activeBranches->first(
                fn (Branch $branch): bool => (int) $branch->getKey() === (int) $selectedBranchId,
            );
        }

        if ($selectedBranch === null && $activeBranches->count() === 1) {
            $onlyBranch = $activeBranches->first();
            if ($onlyBranch instanceof Branch) {
                $onlyBranchId = $onlyBranch->getKey();
                if (is_int($onlyBranchId)) {
                    $selectedBranch = $onlyBranch;
                    $request->session()->put('active_branch_id', $onlyBranchId);
                }
            }
        }

        if ($selectedBranch === null && $selectedBranchId !== null) {
            $request->session()->forget('active_branch_id');
        }

        return view('workspace.index', [
            'company' => $company,
            'activeBranches' => $activeBranches,
            'selectedBranch' => $selectedBranch,
        ]);
    }
}
