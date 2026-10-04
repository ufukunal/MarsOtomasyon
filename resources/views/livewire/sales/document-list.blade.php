<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1>{{ $title }}</h1>
        <a href="{{ route($createRoute) }}">Yeni</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Numara</th>
                <th>Tarih</th>
                <th>Cari</th>
                <th>Durum</th>
                <th>Tutar</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documents as $document)
                <tr wire:key="doc-{{ $document->id }}">
                    <td>{{ $document->number ?? 'Taslak' }}@if($document->revision_no > 0) / Rev.{{ $document->revision_no }}@endif</td>
                    <td>{{ $document->document_date->format('d.m.Y') }}</td>
                    <td>{{ $document->contact?->title }}</td>
                    <td>{{ $document->status }}</td>
                    <td>{{ $document->grand_total }} {{ $document->currency }}</td>
                    <td><button type="button" wire:click="open({{ $document->id }})">Aç</button></td>
                </tr>
            @empty
                <tr><td colspan="6">Kayıt yok.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $documents->links() }}
</div>
