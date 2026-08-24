<?php

namespace App\Modules\Core\Company;

use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveActiveCompany
{
    public function __construct(private readonly ActiveCompanyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $memberships = $user->memberships()
            ->where('is_active', true)
            ->with('company')
            ->get();

        $selectedCompanyId = $request->session()->get('active_company_id');

        if ($selectedCompanyId === null) {
            if ($memberships->count() !== 1) {
                abort(409, 'Aktif şirket seçimi gerekli.');
            }

            /** @var CompanyMembership $membership */
            $membership = $memberships->first();
            $selectedCompanyId = $membership->company_id;
            $request->session()->put('active_company_id', $selectedCompanyId);
        }

        if (! is_int($selectedCompanyId) && ! (is_string($selectedCompanyId) && ctype_digit($selectedCompanyId))) {
            abort(409, 'Aktif şirket seçimi geçersiz.');
        }

        $selectedCompanyId = (int) $selectedCompanyId;

        /** @var CompanyMembership|null $membership */
        $membership = $memberships->first(
            fn (CompanyMembership $candidate): bool => $candidate->company_id === $selectedCompanyId,
        );

        abort_if($membership === null, 403, 'Bu şirkete erişim yetkiniz yok.');

        $this->context->set($membership->company);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
