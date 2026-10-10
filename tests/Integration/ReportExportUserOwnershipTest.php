<?php

use App\Http\Controllers\ReportExportDownloadController;
use App\Models\ReportExportJob;
use App\Models\User;
use App\Support\Reporting\ReportRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('rejects report export downloads requested by another user before accessing storage', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $actor = User::query()->create([
            'name' => 'V4 Export Owner',
            'email' => 'v4-owner-'.Str::random(10).'@invalid.test',
            'password' => 'local-test-password',
            'is_active' => true,
        ]);
        $stranger = User::query()->create([
            'name' => 'V4 Unrelated User',
            'email' => 'v4-stranger-'.Str::random(10).'@invalid.test',
            'password' => 'local-test-password',
            'is_active' => true,
        ]);

        $export = new ReportExportJob([
            'user_id' => $actor->id,
            'company_id' => $companyId,
            'report_key' => 'sales.invoices',
            'format' => 'csv',
            'periods' => [],
            'permission_scope' => [],
            'status' => 'done',
        ]);

        try {
            Auth::login($stranger);
            expect(fn () => app(ReportExportDownloadController::class)->__invoke(
                $export, app(ReportRegistry::class),
            ))->toThrow(AuthorizationException::class);

            Auth::logout();
            expect(fn () => app(ReportExportDownloadController::class)->__invoke(
                $export, app(ReportRegistry::class),
            ))->toThrow(AuthorizationException::class);
        } finally {
            Auth::logout();
        }
    });
});

it('rejects a report export requested in the wrong company context', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $actor = User::query()->create([
            'name' => 'V4 Company Scoped User',
            'email' => 'v4-company-'.Str::random(10).'@invalid.test',
            'password' => 'local-test-password',
            'is_active' => true,
        ]);
        $export = new ReportExportJob([
            'user_id' => $actor->id,
            'company_id' => $companyId + 100,
            'report_key' => 'sales.invoices',
            'format' => 'csv',
            'periods' => [],
            'status' => 'done',
        ]);

        try {
            Auth::login($actor);
            expect(fn () => app(ReportExportDownloadController::class)->__invoke(
                $export, app(ReportRegistry::class),
            ))->toThrow(AuthorizationException::class);
        } finally {
            Auth::logout();
        }
    });
});
