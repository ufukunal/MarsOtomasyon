<?php

namespace App\Listeners;

use App\Support\Audit\AuditContext;
use Illuminate\Auth\Events\Logout;

class AuditLogout
{
    public function handle(Logout $event): void
    {
        AuditContext::master(
            'Kullanıcı çıkış yaptı.',
            ['ip' => request()->ip()],
            $event->user,
            'logout',
        );
    }
}
