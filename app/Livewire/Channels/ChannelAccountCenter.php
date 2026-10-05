<?php

namespace App\Livewire\Channels;

use App\Actions\Channels\SaveChannelAccountPeriodSetting;
use App\Actions\Channels\SaveSalesChannelAccount;
use App\Actions\Channels\SetupTrendyolWebhook;
use App\Actions\Channels\TestChannelConnection;
use App\Enums\SalesChannelPlatform;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\ChannelAccountPeriodSetting;
use App\Models\Period\Contact;
use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelAdapterResolver;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use JsonException;
use Livewire\Component;

class ChannelAccountCenter extends Component
{
    use WithIdempotentMutations;

    public ?int $selectedAccountId = null;
    public int $version = 1;
    public string $platform = 'trendyol';
    public string $name = '';
    public string $externalStoreId = '';
    public string $credentialsJson = '{}';
    public string $settingsJson = '{}';
    public bool $isActive = true;
    public ?int $marketplaceCustomerContactId = null;

    public function mount(): void
    {
        $this->seedMutationKeys(['save', 'periodSetting', 'testConnection', 'setupWebhook']);
        abort_unless(auth()->user()?->can('channel_accounts.view'), 403);
        PeriodContext::ensure();
    }

    public function newAccount(): void
    {
        $this->selectedAccountId = null;
        $this->version = 1;
        $this->platform = SalesChannelPlatform::Trendyol->value;
        $this->name = '';
        $this->externalStoreId = '';
        $this->credentialsJson = '{}';
        $this->settingsJson = '{}';
        $this->isActive = true;
        $this->marketplaceCustomerContactId = null;
    }

    public function selectAccount(int $id): void
    {
        $account = SalesChannelAccount::query()
            ->where('company_id', PeriodContext::companyId())
            ->findOrFail($id);

        $this->selectedAccountId = (int) $account->id;
        $this->version = (int) $account->version;
        $this->platform = $account->platform->value;
        $this->name = (string) $account->name;
        $this->externalStoreId = (string) ($account->external_store_id ?? '');
        $this->credentialsJson = '';
        $this->settingsJson = json_encode(
            $account->settings ?? new \stdClass(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '{}';
        $this->isActive = (bool) $account->is_active;
        $this->marketplaceCustomerContactId = ChannelAccountPeriodSetting::query()
            ->where('channel_account_id', $account->id)
            ->value('marketplace_customer_contact_id');
    }

    public function save(SaveSalesChannelAccount $action): void
    {
        $this->validate([
            'platform' => ['required', 'in:trendyol,hepsiburada,n11,woocommerce'],
            'name' => ['required', 'string', 'max:255'],
            'externalStoreId' => ['nullable', 'string', 'max:255'],
        ]);

        $credentials = $this->credentialsJson === ''
            ? null
            : $this->decodeJsonObject($this->credentialsJson, 'credentialsJson');
        $settings = $this->decodeJsonObject($this->settingsJson ?: '{}', 'settingsJson');
        $existing = $this->selectedAccountId
            ? SalesChannelAccount::query()
                ->where('company_id', PeriodContext::companyId())
                ->findOrFail($this->selectedAccountId)
            : null;

        $saved = $this->runMasterMutation('save', fn () => $action->handle(
            platform: $this->platform,
            name: $this->name,
            externalStoreId: $this->externalStoreId,
            credentials: $credentials,
            settings: $settings,
            isActive: $this->isActive,
            account: $existing,
            expectedVersion: $existing ? $this->version : null,
        ));

        $this->selectAccount((int) $saved->id);
        session()->flash('status', 'Kanal hesabı kaydedildi.');
    }

    public function savePeriodSetting(SaveChannelAccountPeriodSetting $action): void
    {
        abort_unless($this->selectedAccountId !== null && $this->marketplaceCustomerContactId !== null, 422);

        $this->runPeriodMutation('periodSetting', fn () => $action->handle(
            $this->selectedAccountId,
            $this->marketplaceCustomerContactId,
        ));

        session()->flash('status', 'Marketplace müşteri carisi dönem için eşlendi.');
    }

    public function testConnection(TestChannelConnection $action): void
    {
        abort_unless($this->selectedAccountId !== null, 422);
        $account = SalesChannelAccount::query()
            ->where('company_id', PeriodContext::companyId())
            ->findOrFail($this->selectedAccountId);

        $result = $this->runMasterMutation(
            'testConnection',
            function () use ($action, $account): array {
                $operation = $action->handle($account);

                return [
                    'success' => $operation->success,
                    'message' => $operation->message,
                    'external_id' => $operation->externalId,
                ];
            },
        );

        session()->flash(
            $result['success'] ? 'status' : 'warning',
            $result['message'] ?: ($result['success'] ? 'Bağlantı başarılı.' : 'Bağlantı testi başarısız.'),
        );
    }

    public function setupWebhook(SetupTrendyolWebhook $action): void
    {
        abort_unless($this->selectedAccountId !== null, 422);
        $account = SalesChannelAccount::query()
            ->where('company_id', PeriodContext::companyId())
            ->findOrFail($this->selectedAccountId);

        $webhookId = $this->runMasterMutation(
            'setupWebhook',
            fn () => $action->handle($account),
        );

        $this->selectAccount((int) $account->id);
        session()->flash('status', 'Trendyol webhook oluşturuldu: '.$webhookId);
    }

    public function render(ChannelAdapterResolver $resolver): View
    {
        $accounts = SalesChannelAccount::query()
            ->where('company_id', PeriodContext::companyId())
            ->orderBy('platform')
            ->orderBy('name')
            ->get();

        $adapterAvailability = [];
        foreach (SalesChannelPlatform::cases() as $platform) {
            $adapterAvailability[$platform->value] = $resolver->hasAdapter($platform);
        }

        $selectedAccount = $this->selectedAccountId
            ? $accounts->firstWhere('id', $this->selectedAccountId)
            : null;

        return view('livewire.channels.channel-account-center', [
            'accounts' => $accounts,
            'platforms' => SalesChannelPlatform::cases(),
            'contacts' => Contact::query()
                ->where('is_active', true)
                ->orderBy('title')
                ->limit(1000)
                ->get(),
            'adapterAvailability' => $adapterAvailability,
            'selectedAccount' => $selectedAccount,
            'hepsiburadaWebhookBaseUrl' => $selectedAccount?->platform === SalesChannelPlatform::Hepsiburada
                ? url('/hooks/channel/hepsiburada/'.$selectedAccount->id)
                : null,
        ])->layout('layouts.app', ['pageTitle' => 'Kanal Hesapları']);
    }

    /** @return array<string, mixed> */
    private function decodeJsonObject(string $json, string $field): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([$field => 'Geçerli JSON girilmelidir.']);
        }

        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw ValidationException::withMessages([$field => 'JSON object biçiminde olmalıdır.']);
        }

        return $decoded;
    }
}
