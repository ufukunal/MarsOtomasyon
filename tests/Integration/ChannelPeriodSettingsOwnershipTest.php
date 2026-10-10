<?php

use App\Actions\Channels\SaveChannelAccountPeriodSetting;
use App\Models\SalesChannelAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('rejects adding an active period customer to a marketplace account from another company', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        AuthorizedPeriod::login();

        try {
            $foreignCompanyId = DB::connection('master')->table('companies')->insertGetId([
                'code' => 'V4-'.Str::random(10),
                'name' => 'Disposable foreign company',
                'db_prefix' => 'v4_foreign',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $foreign = SalesChannelAccount::query()->create([
                'company_id' => $foreignCompanyId,
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
