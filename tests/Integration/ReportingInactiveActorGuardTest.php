<?php

use App\Actions\Reporting\RunMultiPeriodReport;
use App\Actions\Reporting\RunReport;
use App\Models\User;
use App\Support\Reporting\ReportRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\IsolatedPostgres;

it('refuses anonymous tenant reports before loading report definitions or data', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        auth()->logout();

        expect(fn () => app(RunReport::class)->handle('sales.invoices', new ReportRequest))
            ->toThrow(AuthorizationException::class);
    });
});

it('refuses consolidated queries for inactive users even when a company is supplied', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $user = new User(['name' => 'Disabled V4', 'is_active' => false]);

        expect(fn () => app(RunMultiPeriodReport::class)->handle(
            'sales.invoices', [], new ReportRequest, $user, $companyId,
        ))->toThrow(AuthorizationException::class);
    });
});
