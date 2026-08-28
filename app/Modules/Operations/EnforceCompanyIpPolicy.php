<?php

namespace App\Modules\Operations;

use App\Modules\Core\Company\ActiveCompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforceCompanyIpPolicy
{
    public function __construct(private ActiveCompanyContext $companyContext, private SecurityCenter $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $ip = (string) $request->ip();
        if (! $this->security->ipAllowed($companyId, $ip)) {
            $actorId = $request->user()?->getAuthIdentifier();
            $this->security->record($companyId, is_numeric($actorId) ? (int) $actorId : null, 'security.ip_blocked', 'warning', $ip, $request->userAgent(), ['path' => $request->path()]);
            abort(403);
        }

        return $next($request);
    }
}
