<div class="stack v65-sales-list">
    <div class="form-row v65-list-heading">
        @if($createRoute)
            <a class="button-link table-add-action" href="{{ route($createRoute) }}">+ Yeni</a>
        @endif
    </div>

    <div class="panel"><div class="table-scroll v65-grid-scroll"><table class="data-table">
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
                    <td><button class="button-secondary" type="button" wire:click="open({{ $document->id }})">Aç</button></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">
                    <div>Henüz kayıt yok.</div>
                    @if($createRoute)<a class="button-link" href="{{ route($createRoute) }}">+ Yeni Kayıt</a>@endif
                </td></tr>
            @endforelse
        </tbody>
    </table></div></div>

    {{ $documents->links() }}
</div>
