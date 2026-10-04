<?php

namespace App\Listeners;

use App\Support\Audit\AuditContext;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;

class AuditLogin
{
    public function handle(Login $event): void
    {
        AuditContext::master(
            'Kullanıcı giriş yaptı.',
            ['ip' => request()->ip()],
            $event->user instanceof Model ? $event->user : null,
            'login',
        );
    }
}
