<?php

use App\Actions\Channels\SetupTrendyolWebhook;
use App\Actions\Channels\SetupWooCommerceWebhooks;
use App\Models\SalesChannelAccount;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('prevents installing marketplace webhooks for foreign tenants and wrong platforms', function (string $action, string $platform, bool $foreign): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId) use ($action, $platform, $foreign): void {
        AuthorizedPeriod::login();

        try {
            $account = new SalesChannelAccount([
                'company_id' => $foreign ? $companyId + 100 : $companyId,
                'platform' => $platform,
            ]);
            expect(fn () => app($action)->handle($account))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
})->with([
    [SetupTrendyolWebhook::class, 'woocommerce', false],
    [SetupTrendyolWebhook::class, 'trendyol', true],
    [SetupWooCommerceWebhooks::class, 'trendyol', false],
    [SetupWooCommerceWebhooks::class, 'woocommerce', true],
]);
