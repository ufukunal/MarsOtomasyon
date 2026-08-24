@extends('layouts.settings')

@section('title', 'Numara Serisi Detayı')
@section('heading', 'Numara Serisi Detayı')

@section('content')
    <div class="page-actions">
        <p>Belge numara serisi salt okunur görünümü.</p>
        <div>
            <a href="{{ route('settings.numbering.index') }}">Listeye Dön</a>
            @can('core.settings.manage')
                · <a href="{{ route('settings.numbering.edit', $sequence->getKey()) }}">Düzenle</a>
            @endcan
        </div>
    </div>

    <section class="detail-card">
        <dl class="detail-grid">
            <div>
                <dt>Belge Türü</dt>
                <dd>{{ $sequence->document_type->label() }}</dd>
            </div>
            <div>
                <dt>Seri Kodu</dt>
                <dd>{{ $sequence->series_code }}</dd>
            </div>
            <div>
                <dt>Önek</dt>
                <dd>{{ $sequence->prefix !== '' ? $sequence->prefix : '—' }}</dd>
            </div>
            <div>
                <dt>Basamak</dt>
                <dd>{{ $sequence->padding }}</dd>
            </div>
            <div>
                <dt>Sonraki Numara</dt>
                <dd>{{ $sequence->exampleNumber() }}</dd>
            </div>
            <div>
                <dt>Durum</dt>
                <dd>{{ $sequence->is_active ? 'Aktif' : 'Pasif' }}</dd>
            </div>
        </dl>
    </section>

    <div class="notice-info">
        Belge türü ve seri kodu oluşturulduktan sonra değiştirilmez. Sonraki sıra değeri yalnız ileri taşınabilir.
    </div>
@endsection
