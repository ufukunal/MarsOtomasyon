<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalNetwork
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->isLocal($request->ip()), 403);

        return $next($request);
    }

    private function isLocal(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $long = ip2long($ip);

        return $this->inRange($long, '10.0.0.0', 8)
            || $this->inRange($long, '172.16.0.0', 12)
            || $this->inRange($long, '192.168.0.0', 16);
    }

    private function inRange(int $ip, string $network, int $prefix): bool
    {
        $networkLong = ip2long($network);
        $mask = -1 << (32 - $prefix);

        return ($ip & $mask) === ($networkLong & $mask);
    }
}
