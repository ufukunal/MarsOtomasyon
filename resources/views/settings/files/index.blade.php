@extends('layouts.settings')

@section('title', 'Firma Dosyaları')
@section('heading', 'Firma Dosyaları')

@section('content')
    <div class="page-actions">
        <p>Firmaya ait private dosyalar. Orijinal dosya source-of-truth olarak korunur.</p>
        @can('core.file.manage')
            <a href="{{ route('settings.files.create') }}">Dosya Yükle</a>
        @endcan
    </div>

    <section class="detail-card">
        <table>
            <thead>
            <tr>
                <th>Dosya</th>
                <th>Etiket</th>
                <th>Tür</th>
                <th>Boyut</th>
                <th>Durum</th>
                <th>Yüklenme</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($attachments as $attachment)
                <tr>
                    <td><a href="{{ route('settings.files.show', $attachment->getKey()) }}">{{ $attachment->fileAsset?->original_name ?? 'Dosya' }}</a></td>
                    <td>{{ $attachment->label ?: '—' }}</td>
                    <td>{{ $attachment->fileAsset?->mime_type ?? '—' }}</td>
                    <td>{{ $attachment->fileAsset ? number_format((int) $attachment->fileAsset->size_bytes / 1024, 1, ',', '.') . ' KB' : '—' }}</td>
                    <td>{{ $attachment->isDetached() ? 'Bağlantı Kaldırıldı' : 'Aktif' }}</td>
                    <td>{{ $attachment->attached_at?->format('d.m.Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Henüz firma dosyası yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
