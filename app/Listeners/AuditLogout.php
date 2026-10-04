<?php

namespace App\Listeners;

use App\Support\Audit\AuditContext;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;

class AuditLogout
{
    public function handle(Logout $event): void
    {
        AuditContext::master(
            'Kullanıcı çıkış yaptı.',
            ['ip' => request()->ip()],
            $event->user instanceof Model ? $event->user : null,
            'logout',
        );
    }
}
