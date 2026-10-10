<?php

use App\Actions\Channels\SaveChannelAccountPeriodSetting;
use App\Models\SalesChannelAccount;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('rejects adding an active period customer to a marketplace account from another company', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        AuthorizedPeriod::login();

        try {
            $foreign = SalesChannelAccount::query()->create([
                'company_id' => $companyId + 10000,
                'platform' => 'n11',
                'name' => 'V4 foreign channel account',
                'credentials_encrypted' => [],
                'is_active' => true,
            ]);

            expect(fn () => app(SaveChannelAccountPeriodSetting::class)->handle(
                (int) $foreign->id, 1,
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
