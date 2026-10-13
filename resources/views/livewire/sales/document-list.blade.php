<div class="stack v65-sales-list v65-list-page">
    @teleport('.page-actions.v65-page-actions')
        <div class="v65-list-heading-actions">
            @if($createRoute)
                <a class="button-link" href="{{ route($createRoute) }}">+ Yeni {{ $title }}</a>
            @endif
            <button type="button" class="button-secondary" wire:click="exportCsv">CSV</button>
            <button type="button" class="button-secondary" wire:click="exportXlsx">Excel</button>
        </div>
    @endteleport

    <x-v65.list-filters>
        <label class="v65-list-filter-field">
            <span class="sr-only">Belge numarası veya cari ara</span>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Belge no / cari ara" aria-label="Belge numarası veya cari ara">
        </label>
        <label class="v65-list-filter-field">
            <span class="sr-only">Belge durumu</span>
            <select wire:model.live="status" aria-label="Belge durumu">
                <option value="">Durum · Tümü</option>
                @foreach($statusLabels as $status => $label)
                    <option value="{{ $status }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="v65-list-filter-field">
            <span class="sr-only">Başlangıç tarihi</span>
            <input type="date" wire:model.live="dateFrom" aria-label="Başlangıç tarihi" title="Başlangıç tarihi">
        </label>
        <label class="v65-list-filter-field">
            <span class="sr-only">Bitiş tarihi</span>
            <input type="date" wire:model.live="dateTo" aria-label="Bitiş tarihi" title="Bitiş tarihi">
        </label>
        <button class="button-secondary v65-list-clear" type="button" wire:click="clearFilters">Temizle</button>
    </x-v65.list-filters>

    <div class="v65-list-table table-scroll v65-grid-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    @unless($hiddenColumns['number'] ?? false)<th>Numara</th>@endunless
                    @unless($hiddenColumns['date'] ?? false)<th>Tarih</th>@endunless
                    @unless($hiddenColumns['contact'] ?? false)<th>Cari</th>@endunless
                    @unless($hiddenColumns['status'] ?? false)<th>Durum</th>@endunless
                    @unless($hiddenColumns['total'] ?? false)<th class="align-end">Tutar</th>@endunless
                    <th>Eylem</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    <tr wire:key="doc-{{ $document->id }}">
                        @unless($hiddenColumns['number'] ?? false)
                            <td>{{ $document->number ?? 'Taslak' }}@if($document->revision_no > 0) / Rev.{{ $document->revision_no }}@endif</td>
                        @endunless
                        @unless($hiddenColumns['date'] ?? false)<td>{{ $document->document_date?->format('d.m.Y') ?? '—' }}</td>@endunless
                        @unless($hiddenColumns['contact'] ?? false)<td>{{ $document->contact?->title ?? '—' }}</td>@endunless
                        @unless($hiddenColumns['status'] ?? false)
                            <td><span class="v65-list-status">{{ $statusLabels[$document->status] ?? $document->status }}</span></td>
                        @endunless
                        @unless($hiddenColumns['total'] ?? false)
                            <td class="align-end tabular">{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</td>
                        @endunless
                        <td class="v65-list-row-actions"><button class="button-secondary" type="button" wire:click="open({{ $document->id }})">Aç</button></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 1 + collect(['number','date','contact','status','total'])->reject(fn ($column) => $hiddenColumns[$column] ?? false)->count() }}" class="empty-state">
                        <div>Kayıt bulunamadı.</div>
                        @if($createRoute)
                            <a class="button-link" href="{{ route($createRoute) }}">+ Yeni Kayıt</a>
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-v65.list-footer :paginator="$documents" :per-page="$perPage">
        <x-slot:columnControls>
            <details class="column-picker">
                <summary>Kolonlar</summary>
                <div class="column-picker-menu">
                    @foreach(['number' => 'Numara', 'date' => 'Tarih', 'contact' => 'Cari', 'status' => 'Durum', 'total' => 'Tutar'] as $key => $label)
                        <label>
                            <input type="checkbox" @checked(!($hiddenColumns[$key] ?? false)) wire:click="toggleColumn('{{ $key }}')">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </details>
        </x-slot:columnControls>
    </x-v65.list-footer>
</div>
