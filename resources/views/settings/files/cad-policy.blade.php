@extends('layouts.settings')

@section('title', 'CAD / 3D Cloud Politikası')
@section('heading', 'CAD / 3D Cloud Politikası')

@section('content')
    <div class="notice-info">
        Autodesk APS cloud upload varsayılan olarak kapalıdır. Açılması, şirket teknik dosyalarının üçüncü taraf cloud provider'a gönderilmesine açık idari onay verir.
    </div>

    <form method="post" action="{{ route('settings.files.cad.policy.update') }}" class="detail-card">
        @csrf
        @method('PUT')

        <label>
            <input type="checkbox" name="cloud_upload_enabled" value="1" @checked($policy?->cloud_upload_enabled)>
            Autodesk APS cloud upload kullanımını açıkça etkinleştir
        </label>

        <label>
            Maksimum dosya boyutu (MB)
            <input type="number" name="max_file_size_mb" min="1" max="50" value="{{ $policy ? (int) ceil($policy->max_file_size_bytes / 1048576) : 50 }}" required>
        </label>

        <label>
            Provider timeout bütçesi (saniye)
            <input type="number" name="timeout_seconds" min="30" max="900" value="{{ $policy?->timeout_seconds ?? 300 }}" required>
        </label>

        <label>
            Transient preview retention (gün)
            <input type="number" name="retention_days" min="1" max="7" value="{{ $policy?->retention_days ?? 1 }}" required>
        </label>

        <button type="submit">Politikayı Kaydet</button>
    </form>
@endsection
