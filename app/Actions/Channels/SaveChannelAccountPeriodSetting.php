<?php

namespace App\Actions\Channels;

use App\Models\Period\ChannelAccountPeriodSetting;
use App\Models\Period\Contact;
use App\Models\SalesChannelAccount;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveChannelAccountPeriodSetting
{
    public function handle(
        int $channelAccountId,
        int $marketplaceCustomerContactId,
    ): ChannelAccountPeriodSetting {
        MutationAuthorizer::authorize('channel_accounts.update');
        PeriodContext::ensureWritable();

        $account = SalesChannelAccount::query()->findOrFail($channelAccountId);

        if ((int) $account->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('Kanal hesabı aktif şirkete ait değil.');
        }

        Contact::query()
            ->where('is_active', true)
            ->findOrFail($marketplaceCustomerContactId);

        return DB::connection('period')->transaction(function () use (
            $channelAccountId,
            $marketplaceCustomerContactId,
        ): ChannelAccountPeriodSetting {
            $setting = ChannelAccountPeriodSetting::query()
                ->where('channel_account_id', $channelAccountId)
                ->lockForUpdate()
                ->first();

            if ($setting) {
                $setting->marketplace_customer_contact_id = $marketplaceCustomerContactId;
                $setting->version = (int) $setting->version + 1;
                $setting->save();

                return $setting->refresh();
            }

            return ChannelAccountPeriodSetting::query()->create([
                'channel_account_id' => $channelAccountId,
                'marketplace_customer_contact_id' => $marketplaceCustomerContactId,
                'version' => 1,
            ]);
        }, attempts: 3);
    }
}
