<?php

use App\Actions\Channels\PollChannelAccount;
use App\Actions\Channels\TestChannelConnection;
use App\Actions\Channels\SaveSalesChannelAccount;
use App\Models\SalesChannelAccount;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('never polls an inactive or foreign-company marketplace account', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        AuthorizedPeriod::login();

        try {
            $poller = app(PollChannelAccount::class);
            $inactive = new SalesChannelAccount(['is_active' => false, 'company_id' => $companyId]);
            $foreign = new SalesChannelAccount(['is_active' => true, 'company_id' => $companyId + 9]);

            expect(fn () => $poller->handle($inactive))->toThrow(DomainException::class);
            expect(fn () => $poller->handle($foreign))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects another company channel connection check before reaching provider HTTP', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        AuthorizedPeriod::login();

        try {
            $foreign = new SalesChannelAccount(['company_id' => $companyId + 10]);
            expect(fn () => app(TestChannelConnection::class)->handle($foreign))
                ->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('rejects empty marketplace account names without creating a master record', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            expect(fn () => app(SaveSalesChannelAccount::class)->handle(
                'trendyol', '   ', null, [], [], true,
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('saves a disposable channel account without real marketplace credentials', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        AuthorizedPeriod::login();

        try {
            $name = 'V4 local '.Str::random(6);
            $account = app(SaveSalesChannelAccount::class)->handle(
                'trendyol', $name, null, [], [], false,
            );
            expect($account->company_id)->toBe($companyId)
                ->and($account->name)->toBe($name)
                ->and($account->is_active)->toBeFalse();
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
