<div class="space-y-6 v65-list-page">
    @if(session('status')) <div class="panel">{{ session('status') }}</div> @endif
    <section class="panel">
        <h1>Kanal Sync Merkezi</h1>
        <div class="form-row">
            <select aria-label="Kanal hesabı" wire:model.live="channelAccountId">
                <option value="">Tüm Kanal Hesapları</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->platform->label() }} · {{ $account->name }}</option>
                @endforeach
            </select>
            <select aria-label="Senkronizasyon yönü" wire:model.live="direction">
                <option value="">Tüm Yönler</option>
                <option value="outbound">Outbound</option>
                <option value="inbound">Inbound</option>
            </select>
            <select aria-label="Senkronizasyon durumu" wire:model.live="status">
                <option value="">Tüm Durumlar</option>
                <option value="queued">Queued</option>
                <option value="processing">Processing</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
            </select>
            @can('channel_sync.update')
                <button class="button-secondary" type="button" wire:click="pollNow" @disabled(!$channelAccountId)>Seçili Hesabı Şimdi Poll Et</button>
            @endcan
        </div>

        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>Zaman</th><th>Kanal</th><th>Yön</th><th>Entity</th><th>Action</th><th>Status</th><th>Attempt</th><th>Correlation</th><th>Hata</th><th></th></tr></thead>
            <tbody>
            @forelse($events as $event)
                <tr>
                    <td>{{ $event->created_at?->format('d.m.Y H:i:s') }}</td>
                    <td>{{ $accountNames[$event->channel_account_id] ?? '#'.$event->channel_account_id }}</td>
                    <td>{{ $event->direction }}</td>
                    <td>{{ $event->entity_type }} #{{ $event->entity_id }}</td>
                    <td>{{ $event->action }}</td>
                    <td>{{ $event->status }}</td>
                    <td>{{ $event->attempts }}</td>
                    <td>{{ $event->correlation_id }}</td>
                    <td>{{ $event->error_summary }}</td>
                    <td>
                        @if($event->status === 'failed' && $event->direction === 'outbound')
                            @can('channel_sync.update')
                                <button class="button-secondary" type="button" wire:click="retryEvent({{ $event->id }})">Retry</button>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10">Henüz sync event yok.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="panel">
        <h2>Açık Kalıcı Hatalar</h2>
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>ID</th><th>Event</th><th>Kanal</th><th>Hata</th><th></th></tr></thead>
            <tbody>
            @forelse($errors as $error)
                <tr>
                    <td>{{ $error->id }}</td>
                    <td>#{{ $error->channel_sync_event_id }}</td>
                    <td>{{ $accountNames[$error->event->channel_account_id] ?? '#'.$error->event->channel_account_id }}</td>
                    <td>{{ $error->error_summary }}</td>
                    <td>
                        @can('channel_sync.update')
                            <button class="button-secondary" type="button" wire:click="resolveError({{ $error->id }})">Resolved İşaretle</button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Açık kalıcı hata yok.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div>Webhook destekleyen kanallarda webhook primary, 15 dakikalık polling güvenlik ağıdır. Outbound başarısız sync 30/60/120 saniye politikasına göre otomatik yeniden denenir.</div>
    </section>
</div>
