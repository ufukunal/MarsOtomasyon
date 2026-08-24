@extends('layouts.settings')

@section('title', 'Dosya Yükle')
@section('heading', 'Dosya Yükle')

@section('content')
    <div class="page-actions">
        <p>Dosya private storage alanına kaydedilir; doğrudan public URL oluşturulmaz.</p>
        <a href="{{ route('settings.files.index') }}">Listeye Dön</a>
    </div>

    <section class="form-card">
        <form method="post" action="{{ route('settings.files.store') }}" enctype="multipart/form-data">
            @csrf

            <label>
                Dosya
                <input type="file" name="file" required>
            </label>

            <label>
                Etiket
                <input type="text" name="label" maxlength="160" value="{{ old('label') }}" placeholder="Örn. Vergi levhası">
            </label>

            <p>Azami dosya boyutu 50 MB. Script ve çalıştırılabilir dosya türleri güvenlik nedeniyle kabul edilmez.</p>

            <button type="submit">Dosyayı Yükle</button>
        </form>
    </section>
@endsection
