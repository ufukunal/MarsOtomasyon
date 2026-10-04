<?php

namespace App\Listeners;

use App\Support\Audit\AuditContext;
use Illuminate\Auth\Events\Failed;

class AuditFailedLogin
{
    public function handle(Failed $event): void
    {
        AuditContext::master(
            'Başarısız giriş denemesi.',
            [
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
            ],
            null,
            'failed_login',
        );
    }
}
