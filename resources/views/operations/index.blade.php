@extends('layouts.app')

@section('title', 'Operasyon Merkezi')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">M11 / Integration & Advanced Operations</p>
        <h1>Operasyon Merkezi</h1>
        <p>WooCommerce, Trendyol, bildirim, otomasyon, queue/scheduler, güvenlik ve yedekleme tek merkezden izlenir.</p>
    </div>
    <div class="page-actions"><a class="button-secondary" href="{{ route('search.index') }}">Global Arama</a></div>
</section>

@if(session('status'))<div class="detail-card"><strong>{{ session('status') }}</strong></div>@endif

<section class="detail-card">
    <h2>Sistem Sağlığı</h2>
    <div class="form-grid">
        <div><strong>PostgreSQL</strong><br>{{ $health['database_ok'] ? 'OK' : 'HATA' }}</div>
        <div><strong>Valkey</strong><br>{{ $health['valkey_ok'] ? 'OK' : 'HATA' }}</div>
        <div><strong>Queue Depth</strong><br>{{ $health['queue_depth'] }}</div>
        <div><strong>Failed Jobs</strong><br>{{ $health['failed_jobs'] }}</div>
        <div><strong>Worker</strong><br>{{ $health['worker_alive'] ? 'Aktif' : 'Heartbeat yok' }}</div>
        <div><strong>Scheduler</strong><br>{{ $health['scheduler_alive'] ? 'Aktif' : 'Heartbeat yok' }}</div>
        <div><strong>Entegrasyon Bekleyen</strong><br>{{ $health['integration_pending'] }}</div>
        <div><strong>Bildirim Bekleyen</strong><br>{{ $health['notification_pending'] }}</div>
        <div><strong>Otomasyon Bekleyen</strong><br>{{ $health['automation_pending'] }}</div>
    </div>
</section>

@can('integrations.manage')
<section class="detail-card">
    <h2>Kanal Bağlantısı</h2>
    <form method="post" action="{{ route('operations.connections.store') }}">@csrf
        <div class="form-grid">
            <label>Provider<select name="provider" required><option value="woocommerce">WooCommerce</option><option value="trendyol">Trendyol</option></select></label>
            <label>Ad<input name="name" required maxlength="96"></label>
            <label>Base URL<input name="base_url" type="url"></label>
            <label>Webhook Secret<input name="webhook_secret" type="password" required minlength="16"></label>
            <label>API Key / Consumer Key<input name="credentials[api_key]"></label>
            <label>API Secret<input name="credentials[api_secret]" type="password"></label>
            <label>Woo Consumer Key<input name="credentials[consumer_key]"></label>
            <label>Woo Consumer Secret<input name="credentials[consumer_secret]" type="password"></label>
        </div>
        <p>Trendyol özel operasyon endpointleri gerektiğinde <code>credentials[endpoints][operation]</code> API üzerinden tanımlanabilir.</p>
        <button class="button-primary" type="submit">Bağlantıyı Kaydet</button>
    </form>
</section>
@endcan

<section class="statement-table-card">
<h2>Bağlantılar</h2>
<table class="data-table"><thead><tr><th>Provider</th><th>Ad</th><th>Durum</th><th>Son Sync</th><th>Son Hata</th></tr></thead><tbody>
@forelse($connections as $connection)<tr><td>{{ $connection->provider }}</td><td>{{ $connection->name }}</td><td>{{ $connection->status }}</td><td>{{ $connection->last_sync_at }}</td><td>{{ $connection->last_error }}</td></tr>@empty<tr><td colspan="5">Bağlantı yok.</td></tr>@endforelse
</tbody></table>
</section>

<section class="statement-table-card">
<h2>Integration Inbox</h2>
<table class="data-table"><thead><tr><th>ID</th><th>Event</th><th>External ID</th><th>Durum</th><th>Deneme</th><th></th></tr></thead><tbody>
@forelse($integrationEvents as $event)<tr><td>{{ $event->id }}</td><td>{{ $event->event_type }}</td><td>{{ $event->external_event_id }}</td><td>{{ $event->status }}</td><td>{{ $event->attempts }}</td><td>@can('operations.manage')<form method="post" action="{{ route('operations.retry', ['type'=>'event','id'=>$event->id]) }}">@csrf<button class="button-secondary">Retry</button></form>@endcan</td></tr>@empty<tr><td colspan="6">Event yok.</td></tr>@endforelse
</tbody></table>
<div class="page-actions"><a class="button-secondary" href="{{ route('operations.export', 'integration-events') }}">CSV Export</a></div>
</section>

<section class="statement-table-card">
<h2>Integration Outbox</h2>
<table class="data-table"><thead><tr><th>ID</th><th>Operasyon</th><th>Entity</th><th>Durum</th><th>Deneme</th><th></th></tr></thead><tbody>
@forelse($syncEffects as $sync)<tr><td>{{ $sync->id }}</td><td>{{ $sync->operation }}</td><td>{{ $sync->entity_type }}:{{ $sync->entity_id }}</td><td>{{ $sync->status }}</td><td>{{ $sync->attempts }}</td><td>@can('operations.manage')<form method="post" action="{{ route('operations.retry', ['type'=>'sync','id'=>$sync->id]) }}">@csrf<button class="button-secondary">Retry</button></form>@endcan</td></tr>@empty<tr><td colspan="6">Sync işi yok.</td></tr>@endforelse
</tbody></table>
</section>

@can('notifications.manage')
<section class="detail-card">
<h2>Bildirim Şablonu</h2>
<form method="post" action="{{ route('operations.templates.store') }}">@csrf
<div class="form-grid"><label>Key<input name="key" required></label><label>Kanal<select name="channel"><option>email</option><option>sms</option><option>whatsapp</option></select></label><label>Ad<input name="name" required></label><label>Konu<input name="subject"></label></div>
<label>Gövde<textarea name="body" rows="4" required placeholder="Merhaba {{ '{{name}}' }}"></textarea></label>
<button class="button-primary">Şablonu Kaydet</button></form>
</section>
@endcan

<section class="statement-table-card"><h2>Bildirim Teslimatları</h2><table class="data-table"><thead><tr><th>ID</th><th>Kanal</th><th>Alıcı</th><th>Durum</th><th>Deneme</th><th></th></tr></thead><tbody>
@forelse($deliveries as $delivery)<tr><td>{{ $delivery->id }}</td><td>{{ $delivery->channel }}</td><td>{{ $delivery->recipient }}</td><td>{{ $delivery->status }}</td><td>{{ $delivery->attempts }}</td><td>@can('operations.manage')<form method="post" action="{{ route('operations.retry', ['type'=>'notification','id'=>$delivery->id]) }}">@csrf<button class="button-secondary">Retry</button></form>@endcan</td></tr>@empty<tr><td colspan="6">Teslimat yok.</td></tr>@endforelse
</tbody></table><div class="page-actions"><a class="button-secondary" href="{{ route('operations.export', 'notification-deliveries') }}">CSV Export</a></div></section>

@can('automation.manage')
<section class="detail-card"><h2>Otomasyon Kuralı</h2><form method="post" action="{{ route('operations.automation-rules.store') }}">@csrf
<div class="form-grid"><label>Key<input name="key" required></label><label>Ad<input name="name" required></label><label>Event<input name="event_type" placeholder="channel.order.created" required></label><label>Aksiyon<select name="action_type"><option value="notify">notify</option><option value="integration_sync">integration_sync</option><option value="security_event">security_event</option></select></label><label>Öncelik<input name="priority" type="number" value="100"></label><label><input name="requires_approval" type="checkbox" value="1"> Onay gerekli</label></div>
<label>Aksiyon Payload JSON<textarea name="action_payload_json" rows="4" placeholder='{"template_key":"order.created","channel":"email","recipient":"$.email"}'></textarea></label>
<label>Koşullar JSON<textarea name="conditions_json" rows=3 placeholder='{"status":"paid"}'></textarea></label>
<p>UI dışı API istemcileri doğrudan <code>action_payload</code> array gönderebilir.</p>
<button class="button-primary" type="submit">Otomasyon Kuralını Kaydet</button>
</form></section>
@endcan

<section class="statement-table-card"><h2>Otomasyon Çalışmaları</h2><table class="data-table"><thead><tr><th>ID</th><th>Rule</th><th>Trigger</th><th>Durum</th><th>Onay</th><th></th></tr></thead><tbody>
@forelse($runs as $run)<tr><td>{{ $run->id }}</td><td>{{ $run->rule_id }}</td><td>{{ $run->trigger_key }}</td><td>{{ $run->status }}</td><td>{{ $run->approved_at }}</td><td>@if($run->status==='pending_approval') @can('automation.manage')<form method="post" action="{{ route('operations.automation-runs.approve',$run->id) }}">@csrf<button class="button-primary">Onayla</button></form><form method="post" action="{{ route('operations.automation-runs.reject',$run->id) }}">@csrf<button class="button-secondary">Reddet</button></form>@endcan @elseif($run->status==='failed') @can('operations.manage')<form method="post" action="{{ route('operations.retry',['type'=>'automation','id'=>$run->id]) }}">@csrf<button class="button-secondary">Retry</button></form>@endcan @endif</td></tr>@empty<tr><td colspan="6">Run yok.</td></tr>@endforelse
</tbody></table><div class="page-actions"><a class="button-secondary" href="{{ route('operations.export','automation-runs') }}">CSV Export</a></div></section>

@can('backups.manage')
<section class="detail-card"><h2>Yedek / Restore</h2><form method="post" action="{{ route('operations.backups.create') }}">@csrf<button class="button-primary">Şifreli .marsbak Oluştur</button></form></section>
@endcan
<section class="statement-table-card"><table class="data-table"><thead><tr><th>ID</th><th>Durum</th><th>Boyut</th><th>SHA-256</th><th>Doğrulama</th><th></th></tr></thead><tbody>
@forelse($backups as $backup)<tr><td>{{ $backup->id }}</td><td>{{ $backup->status }}</td><td>{{ $backup->size_bytes }}</td><td><code>{{ $backup->sha256 }}</code></td><td>{{ $backup->verified_at }}</td><td>@can('backups.view')<form method="post" action="{{ route('operations.backups.verify',$backup->id) }}">@csrf<button class="button-secondary">Verify</button></form>@endcan @can('backups.manage')@if($backup->status==='ready')<form method="post" action="{{ route('operations.backups.restore',$backup->id) }}">@csrf<input type="hidden" name="confirm" value="RESTORE"><button class="button-secondary">Restore + Safety Backup</button></form>@endif@endcan</td></tr>@empty<tr><td colspan="6">Yedek yok.</td></tr>@endforelse
</tbody></table></section>

@can('security.manage')
<section class="detail-card"><h2>IP Erişim Kuralı</h2><form method="post" action="{{ route('operations.ip-rules.store') }}">@csrf<div class="form-grid"><label>Aksiyon<select name="action"><option>deny</option><option>allow</option></select></label><label>CIDR<input name="cidr" placeholder="192.0.2.0/24" required></label><label>Etiket<input name="label"></label></div><button class="button-primary">Kural Ekle</button></form></section>
@endcan
<section class="statement-table-card"><h2>Güvenlik Olayları</h2><table class="data-table"><thead><tr><th>ID</th><th>Tip</th><th>Seviye</th><th>IP</th><th>Zaman</th></tr></thead><tbody>@forelse($securityEvents as $event)<tr><td>{{ $event->id }}</td><td>{{ $event->event_type }}</td><td>{{ $event->severity }}</td><td>{{ $event->ip_address }}</td><td>{{ $event->created_at }}</td></tr>@empty<tr><td colspan="5">Güvenlik olayı yok.</td></tr>@endforelse</tbody></table><div class="page-actions"><a class="button-secondary" href="{{ route('operations.export','security-events') }}">CSV Export</a></div></section>
@endsection
