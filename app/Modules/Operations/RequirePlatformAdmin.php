<?php

namespace App\Modules\Operations;

use App\Modules\Core\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequirePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isPlatformAdmin(), 403, 'Bu işlem platform yöneticisi yetkisi gerektirir.');

        return $next($request);
    }
}
