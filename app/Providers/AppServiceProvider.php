<?php

namespace App\Providers;

use App\Listeners\AuditFailedLogin;
use App\Listeners\AuditLogin;
use App\Listeners\AuditLogout;
use App\Listeners\PrepareBackupSources;
use App\Models\Attachment;
use App\Models\Period\Contact;
use App\Models\Period\Product;
use App\Policies\ProductPolicy;
use App\Policies\ContactPolicy;
use App\Observers\AttachmentObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(app()->isLocal());

        DB::listen(function ($query): void {
            if ($query->time > 100) {
                Log::warning('Yavaş sorgu', [
                    'connection' => $query->connectionName,
                    'sql' => $query->sql,
                    'duration_ms' => $query->time,
                ]);
            }
        });

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip(),
            );
        });

        RateLimiter::for('password-reset', fn (Request $request): Limit =>
            Limit::perHour(3)->by($request->ip()));

        RateLimiter::for('upload', fn (Request $request): Limit =>
            Limit::perMinute(30)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('report', fn (Request $request): Limit =>
            Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('webhook', fn (Request $request): Limit =>
            Limit::perMinute(120)->by($request->ip()));
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        Attachment::observe(AttachmentObserver::class);

        Event::listen(Login::class, AuditLogin::class);
        Event::listen(Logout::class, AuditLogout::class);
        Event::listen(Failed::class, AuditFailedLogin::class);
        Event::listen(CommandStarting::class, PrepareBackupSources::class);
    }
}
