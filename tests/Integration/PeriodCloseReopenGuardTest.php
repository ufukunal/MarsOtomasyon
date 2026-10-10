<?php

use App\Actions\Periods\ClosePeriod;
use App\Actions\Periods\ReopenPeriod;
use App\Models\Period;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('rejects closing a period that belongs to a different company before checking its source database', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $foreign = new Period(['company_id' => $companyId + 999, 'status' => 'active']);
        expect(fn () => app(ClosePeriod::class)->handle($foreign))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(ReopenPeriod::class)->handle($foreign, 'restore'))
            ->toThrow(AuthorizationException::class);
    });
});

it('refuses archived periods, and requires an audit reason to reopen', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $periodId): void {
        AuthorizedPeriod::login();

        try {
            $period = Period::query()->findOrFail($periodId);

            expect(fn () => app(ReopenPeriod::class)->handle($period, '  '))
                ->toThrow(InvalidArgumentException::class);

            $period->status = 'archived';
            $period->save();

            expect(fn () => app(ClosePeriod::class)->handle($period))
                ->toThrow(DomainException::class);
            expect(fn () => app(ReopenPeriod::class)->handle($period, 'audit approved'))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
