@extends('layouts.settings')

@section('title', 'Numaralandırma')
@section('heading', 'Numaralandırma')

@section('content')
    <div class="page-actions">
        <p>Belge türleri için şirket bazlı numara serileri.</p>
        @can('core.settings.manage')
            <a class="button-primary" href="{{ route('settings.numbering.create') }}">Yeni Seri</a>
        @endcan
    </div>

    <section class="table-card">
        <table>
            <thead>
            <tr>
                <th>Belge</th>
                <th>Seri</th>
                <th>Sonraki Numara</th>
                <th>Durum</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($sequences as $sequence)
                <tr>
                    <td>{{ $sequence->document_type->label() }}</td>
                    <td>{{ $sequence->series_code }}</td>
                    <td>{{ $sequence->exampleNumber() }}</td>
                    <td>{{ $sequence->is_active ? 'Aktif' : 'Pasif' }}</td>
                    <td><a href="{{ route('settings.numbering.show', $sequence->getKey()) }}">Detay</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Henüz numara serisi tanımlanmadı.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
