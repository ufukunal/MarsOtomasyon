@extends('layouts.settings')

@section('title', 'Entegrasyonlar')
@section('heading', 'Entegrasyonlar')

@section('content')
    <div class="page-actions">
        <p>Credential değerleri şifreli saklanır ve kaydettikten sonra tekrar gösterilmez. “Yapılandırma doğrulandı” production provider bağlantısının doğrulandığı anlamına gelmez.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="settings-grid">
        @foreach ($integrations as $integration)
            <section class="settings-tile" style="display:block">
                <strong>{{ match($integration->family) { 'sms' => 'SMS', 'email' => 'E-Posta', 'whatsapp' => 'WhatsApp', 'e_document' => 'E-Belge', 'scanner_agent' => 'Scanner Agent', default => $integration->family } }}</strong>
                <p>Durum: {{ $integration->isEnabled ? 'Aktif' : 'Pasif' }} · Doğrulama: {{ $integration->verificationStatus }} · Credential: {{ $integration->hasCredentials ? 'kayıtlı' : 'yok' }}</p>
                <form method="POST" action="{{ route('settings.integrations.update', $integration->family) }}">
                    @csrf
                    @method('PUT')
                    <label>Provider key <input name="provider_key" value="{{ $integration->providerKey }}" placeholder="provider_key"></label>
                    <label>Endpoint <input name="endpoint_url" value="{{ $integration->endpointUrl }}" placeholder="https://..."></label>
                    <label>Ayarlar JSON <textarea name="settings_json">{{ $integration->settings }}</textarea></label>
                    <label>Credential JSON <textarea name="credentials_json" placeholder='{"api_key":"..."}'></textarea></label>
                    <input type="hidden" name="is_enabled" value="0">
                    <label><input type="checkbox" name="is_enabled" value="1" @checked($integration->isEnabled)> Aktif</label>
                    <button type="submit">Kaydet</button>
                </form>
                <form method="POST" action="{{ route('settings.integrations.validate', $integration->family) }}" style="margin-top:.5rem">
                    @csrf
                    <button type="submit">Yapılandırmayı doğrula</button>
                </form>
            </section>
        @endforeach
    </div>

    <h2>Bildirim şablonu</h2>
    <form method="POST" action="{{ route('settings.integrations.templates.store') }}" class="stack-form">
        @csrf
        <label>Key <input name="key" required placeholder="order.ready"></label>
        <label>Kanal <select name="channel"><option value="email">E-Posta</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></label>
        <label>Ad <input name="name" required></label>
        <label>Konu <input name="subject"></label>
        <label>Gövde <textarea name="body" required></textarea></label>
        <label>Değişkenler <input name="variables" placeholder="number,customer_name"></label>
        <button type="submit">Yeni versiyon kaydet</button>
    </form>

    <h3>Mevcut şablonlar</h3>
    <table>
        <thead><tr><th>Kanal</th><th>Key</th><th>Ad</th><th>Versiyon</th><th>Durum</th></tr></thead>
        <tbody>
        @foreach ($templates as $template)
            <tr><td>{{ $template->channel }}</td><td>{{ $template->key }}</td><td>{{ $template->name }}</td><td>v{{ $template->current_version }}</td><td>{{ $template->status }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <h2>Şablon önizleme / test render</h2>
    <form method="POST" action="{{ route('settings.integrations.templates.preview') }}" class="stack-form">
        @csrf
        <label>Konu <input name="subject"></label>
        <label>Gövde <textarea name="body" required></textarea></label>
        <label>Değişken JSON <textarea name="variables_json" placeholder='{"number":"SO-1"}'></textarea></label>
        <button type="submit">Önizle</button>
    </form>

    <h3>Son provider denemeleri</h3>
    <table>
        <thead><tr><th>Delivery</th><th>Deneme</th><th>Provider</th><th>Durum</th><th>Başlangıç</th></tr></thead>
        <tbody>
        @foreach ($attempts as $attempt)
            <tr><td>{{ $attempt->delivery_id }}</td><td>{{ $attempt->attempt_no }}</td><td>{{ $attempt->provider }}</td><td>{{ $attempt->status }}</td><td>{{ $attempt->started_at }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endsection
