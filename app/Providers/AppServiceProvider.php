<?php

namespace App\Providers;

use App\Listeners\AuditFailedLogin;
use App\Listeners\AuditLogin;
use App\Listeners\AuditLogout;
use App\Models\Attachment;
use App\Observers\AttachmentObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Attachment::observe(AttachmentObserver::class);

        Event::listen(Login::class, AuditLogin::class);
        Event::listen(Logout::class, AuditLogout::class);
        Event::listen(Failed::class, AuditFailedLogin::class);
    }
}
