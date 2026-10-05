<div class="space-y-6">
    @if(session('status')) <div class="panel">{{ session('status') }}</div> @endif
    @if(session('warning')) <div class="panel">{{ session('warning') }}</div> @endif

    <section class="panel">
        <div class="form-row">
            <h1>Kanal Hesapları</h1>
            @can('channel_accounts.create')
                <button type="button" wire:click="newAccount">Yeni Hesap</button>
            @endcan
        </div>

        <table class="data-table">
            <thead><tr><th>Platform</th><th>Hesap</th><th>Store ID</th><th>Aktif</th><th>Adapter</th><th></th></tr></thead>
            <tbody>
            @foreach($accounts as $account)
                <tr>
                    <td>{{ $account->platform->label() }}</td>
                    <td>{{ $account->name }}</td>
                    <td>{{ $account->external_store_id }}</td>
                    <td>{{ $account->is_active ? 'Evet' : 'Hayır' }}</td>
                    <td>{{ $adapterAvailability[$account->platform->value] ? 'Hazır' : 'Kanal bloğu bekleniyor' }}</td>
                    <td><button type="button" wire:click="selectAccount({{ $account->id }})">Aç</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section class="panel space-y-3">
        <h2>{{ $selectedAccountId ? 'Kanal Hesabını Düzenle' : 'Yeni Kanal Hesabı' }}</h2>
        <label>Platform
            <select wire:model="platform" @disabled($selectedAccountId)>
                @foreach($platforms as $item)
                    <option value="{{ $item->value }}">{{ $item->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>Hesap / Mağaza Adı <input wire:model="name"></label>
        <label>External Store ID <input wire:model="externalStoreId"></label>
        <label>
            Credential JSON
            <textarea wire:model="credentialsJson" placeholder="{{ $selectedAccountId ? 'Boş bırakılırsa mevcut şifreli credential korunur' : '{}' }}"></textarea>
        </label>
        <small>Credential değerleri kaydedildikten sonra ekrana geri basılmaz.</small>
        <label>Adapter Settings JSON <textarea wire:model="settingsJson"></textarea></label>
        <small>Token, secret, password ve API key gibi hassas değerler settings alanına kabul edilmez; Credential JSON kullanılmalıdır.</small>
        <label><input type="checkbox" wire:model="isActive"> Aktif</label>

        @canany(['channel_accounts.create','channel_accounts.update'])
            <button type="button" wire:click="save">Kaydet</button>
        @endcanany

        @if($selectedAccountId)
            <hr>
            <h3>Dönem Marketplace Müşteri Carisi</h3>
            <select wire:model="marketplaceCustomerContactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
            @can('channel_accounts.update')
                <button type="button" wire:click="savePeriodSetting">Dönem Eşlemesini Kaydet</button>
            @endcan

            @if($adapterAvailability[$platform] ?? false)
                @can('channel_accounts.update')
                    <button type="button" wire:click="testConnection">Bağlantıyı Test Et</button>
                    @if($platform === 'trendyol')
                        @if(data_get($selectedAccount?->settings, 'trendyol_webhook_id'))
                            <span>Webhook ID: {{ data_get($selectedAccount?->settings, 'trendyol_webhook_id') }}</span>
                        @else
                            <button type="button" wire:click="setupWebhook">Webhook Oluştur</button>
                        @endif
                    @elseif($platform === 'hepsiburada')
                        <div class="space-y-2">
                            <strong>Hepsiburada Webhook Base URL</strong>
                            <code>{{ $hepsiburadaWebhookBaseUrl }}</code>
                            <div>
                                Hepsiburada entegratör ayarında bu adres Base URL olarak tanımlanır;
                                platform createOrder, orderCancel, awaitingAction ve diğer event adlarını URL sonuna ekler.
                            </div>
                            <div>
                                External Store ID = MerchantId. Credential JSON içinde
                                <code>username</code> + <code>password</code> (veya <code>service_key</code>)
                                ile webhook receiver için ayrı <code>webhook_username</code> +
                                <code>webhook_password</code> tutulmalıdır.
                            </div>
                            <div>
                                Settings JSON: <code>environment</code> = <code>sit</code> veya <code>prod</code>,
                                isteğe bağlı <code>integrator_name</code>.
                            </div>
                        </div>
                    @endif
                @endcan
            @else
                <div>Bu platformun gerçek adapter'ı henüz eklenmedi.</div>
            @endif
        @endif
    </section>

    @foreach($errors->all() as $error)
        <div class="field-error">{{ $error }}</div>
    @endforeach
</div>
