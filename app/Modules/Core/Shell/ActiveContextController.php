<?php

namespace App\Modules\Core\Shell;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\CompanyStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final readonly class ActiveContextController
{
    public function __construct(private ActiveCompanyContext $companyContext) {}

    public function entry(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $memberships = $this->activeMemberships($user);
        abort_if($memberships->isEmpty(), 403, 'Erişebileceğiniz aktif bir şirket bulunmuyor.');

        $selectedCompanyId = $this->sessionId($request, 'active_company_id');
        if ($selectedCompanyId !== null && $memberships->contains(
            fn (CompanyMembership $membership): bool => $membership->company_id === $selectedCompanyId,
        )) {
            return redirect()->route('workspace');
        }

        $request->session()->forget(['active_company_id', 'active_branch_id']);

        if ($memberships->count() === 1) {
            $membership = $memberships->first();
            if (! $membership instanceof CompanyMembership) {
                abort(409, 'Şirket seçimi çözülemedi.');
            }

            $request->session()->put('active_company_id', $membership->company_id);

            return redirect()->route('workspace');
        }

        return redirect()->route('context.companies');
    }

    public function companies(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $memberships = $this->activeMemberships($user);
        abort_if($memberships->isEmpty(), 403, 'Erişebileceğiniz aktif bir şirket bulunmuyor.');

        return view('context.companies', ['memberships' => $memberships]);
    }

    public function selectCompany(Request $request, int $company): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $membership = $this->activeMemberships($user)->first(
            fn (CompanyMembership $candidate): bool => $candidate->company_id === $company,
        );
        abort_if($membership === null, 404);

        $request->session()->put('active_company_id', $company);
        $request->session()->forget('active_branch_id');

        return redirect()->route('workspace')->with('status', 'Aktif firma değiştirildi.');
    }

    public function selectBranch(Request $request, int $branch): RedirectResponse
    {
        $company = $this->companyContext->requireCompany();
        $companyId = $company->getKey();
        abort_unless(is_int($companyId), 409, 'Aktif şirket geçerli değil.');

        $selected = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($branch);

        $selectedId = $selected->getKey();
        abort_unless(is_int($selectedId), 409, 'Aktif şube geçerli değil.');

        $request->session()->put('active_branch_id', $selectedId);

        return redirect()->route('workspace')->with('status', 'Aktif şube değiştirildi.');
    }

    /** @return Collection<int, CompanyMembership> */
    private function activeMemberships(User $user): Collection
    {
        /** @var Collection<int, CompanyMembership> $memberships */
        $memberships = $user->memberships()
            ->where('is_active', true)
            ->with('company')
            ->get()
            ->filter(
                fn (CompanyMembership $membership): bool => $membership->company?->status === CompanyStatus::Active,
            )
            ->values();

        return $memberships;
    }

    private function sessionId(Request $request, string $key): ?int
    {
        $value = $request->session()->get($key);

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }
}
