<?php

namespace App\Modules\Operations;

use DomainException;
use Illuminate\Support\Facades\DB;

final class SecurityCenter
{
    /** @param array<string,mixed>|null $context */
    public function record(?int $companyId, ?int $actorUserId, string $eventType, string $severity = 'info', ?string $ip = null, ?string $userAgent = null, ?array $context = null): int
    {
        if (! in_array($severity, ['info', 'warning', 'critical'], true)) {
            throw new DomainException('Invalid security event severity.');
        }

        return (int) DB::table('security_events')->insertGetId([
            'company_id' => $companyId,
            'actor_user_id' => $actorUserId,
            'event_type' => trim($eventType),
            'severity' => $severity,
            'ip_address' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 512),
            'context' => $context === null ? null : json_encode($context, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public function ipAllowed(int $companyId, string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $rules = DB::table('security_ip_rules')->where('company_id', $companyId)->where('is_active', true)->get();
        $hasAllow = false;
        foreach ($rules as $rule) {
            $action = (string) $rule->action;
            $matches = $this->cidrContains((string) $rule->cidr, $ip);
            if ($action === 'deny' && $matches) {
                return false;
            }
            if ($action === 'allow') {
                $hasAllow = true;
                if ($matches) {
                    return true;
                }
            }
        }

        return ! $hasAllow;
    }

    private function cidrContains(string $cidr, string $ip): bool
    {
        if (! str_contains($cidr, '/')) {
            return $cidr === $ip;
        }
        [$network, $prefix] = explode('/', $cidr, 2);
        $networkPacked = inet_pton($network);
        $ipPacked = inet_pton($ip);
        if ($networkPacked === false || $ipPacked === false || strlen($networkPacked) !== strlen($ipPacked) || ! ctype_digit($prefix)) {
            return false;
        }
        $bits = (int) $prefix;
        $maxBits = strlen($networkPacked) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }
        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;
        if ($wholeBytes > 0 && substr($networkPacked, 0, $wholeBytes) !== substr($ipPacked, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($networkPacked[$wholeBytes]) & $mask) === (ord($ipPacked[$wholeBytes]) & $mask);
    }
}
