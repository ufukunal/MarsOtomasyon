<?php

namespace App\Modules\Core\Branch;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Branch;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveActiveBranch
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private ActiveBranchContext $branchContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $this->companyContext->id();
        if ($companyId === null) {
            abort(409, 'Aktif şirket context bulunamadı.');
        }

        /** @var Collection<int, Branch> $branches */
        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $selectedBranchId = $request->session()->get('active_branch_id');

        if ($selectedBranchId === null) {
            if ($branches->isEmpty()) {
                abort(409, 'Aktif şube bulunamadı.');
            }

            if ($branches->count() !== 1) {
                abort(409, 'Aktif şube seçimi gerekli.');
            }

            $onlyBranch = $branches->sole();
            $onlyBranchId = $onlyBranch->getKey();
            if (! is_int($onlyBranchId)) {
                abort(409, 'Aktif şube seçimi geçersiz.');
            }

            $selectedBranchId = $onlyBranchId;
            $request->session()->put('active_branch_id', $selectedBranchId);
        }

        if (! is_int($selectedBranchId) && ! (is_string($selectedBranchId) && ctype_digit($selectedBranchId))) {
            abort(409, 'Aktif şube seçimi geçersiz.');
        }

        $selectedBranchId = (int) $selectedBranchId;
        $branch = $branches->first(
            fn (Branch $candidate): bool => (int) $candidate->getKey() === $selectedBranchId,
        );

        if (! $branch instanceof Branch) {
            abort(403, 'Bu şubeye erişim yetkiniz yok veya şube pasif.');
        }

        $this->branchContext->set($branch);

        try {
            return $next($request);
        } finally {
            $this->branchContext->clear();
        }
    }
}
