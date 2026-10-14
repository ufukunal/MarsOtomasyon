<div class="stack v65-sales-list v65-list-page">
    @teleport('.page-actions.v65-page-actions')
        <div class="v65-list-heading-actions">
            @if($createRoute)
                <a class="button-link" href="{{ route($createRoute) }}">+ Yeni {{ $title }}</a>
            @endif
            <button type="button" class="button-secondary" onclick="window.print()">Yazdır</button>
            <x-v65.action-menu label="Dışa Aktar">
                <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv">CSV</button>
                <button type="button" wire:click="exportXlsx" wire:loading.attr="disabled" wire:target="exportXlsx">Excel</button>
            </x-v65.action-menu>
        </div>
    @endteleport

    <x-v65.list-heading :title="$title">
        <div class="v65-filter-toolbar">
        <x-v65.filter-drawer :count="$this->activeV65FilterCount()" :tab="$v65FilterTab">
            <x-slot:filters>
                <x-v65.filter-field label="Genel Arama">
                    <input type="search" wire:model="v65DraftFilters.search" maxlength="255" placeholder="Belge no / cari ara">
                </x-v65.filter-field>
                <x-v65.filter-field label="Belge Durumu">
                    <select wire:model="v65DraftFilters.filters.status">
                        <option value="">Tümü</option>
                        @foreach($statusLabels as $status => $label)
                            <option value="{{ $status }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-v65.filter-field>
                <x-v65.filter-field label="Belge Tarihi">
                    <div class="v65-filter-date-pair">
                        <input type="date" aria-label="Başlangıç tarihi" wire:model="v65DraftFilters.filters.date.from">
                        <input type="date" aria-label="Bitiş tarihi" wire:model="v65DraftFilters.filters.date.to">
                    </div>
                </x-v65.filter-field>
            </x-slot:filters>
            <x-slot:sorting>
                <x-v65.filter-field label="Sıralama Alanı">
                    <select wire:model="v65DraftFilters.sort">
                        <option value="">Varsayılan (en yeni)</option>
                        @foreach($this->v65AvailableSortOptions() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-v65.filter-field>
                <x-v65.filter-field label="Sıralama Yönü">
                    <select wire:model="v65DraftFilters.direction">
                        <option value="asc">Artan</option>
                        <option value="desc">Azalan</option>
                    </select>
                </x-v65.filter-field>
            </x-slot:sorting>
        </x-v65.filter-drawer>
    </div>
    </x-v65.list-heading>
    <x-v65.active-filters :items="$this->v65ActiveFilterLabels()" />

    <div class="v65-list-table table-scroll v65-grid-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    @unless($hiddenColumns['number'] ?? false)<th>Numara</th>@endunless
                    @unless($hiddenColumns['date'] ?? false)<th>Tarih</th>@endunless
                    @unless($hiddenColumns['contact'] ?? false)<th>Cari</th>@endunless
                    @unless($hiddenColumns['status'] ?? false)<th>Durum</th>@endunless
                    @unless($hiddenColumns['total'] ?? false)<th class="align-end">Tutar</th>@endunless
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    <tr wire:key="doc-{{ $document->id }}">
                        @unless($hiddenColumns['number'] ?? false)
                            <td><a class="v65-record-link" href="{{ route($detailRoute, ['id' => $document->id]) }}">{{ $document->number ?? 'Taslak' }}@if($document->revision_no > 0) / Rev.{{ $document->revision_no }}@endif</a></td>
                        @endunless
                        @unless($hiddenColumns['date'] ?? false)<td>{{ $document->document_date?->format('d.m.Y') ?? '—' }}</td>@endunless
                        @unless($hiddenColumns['contact'] ?? false)
                            <td>
                                @if($document->contact && auth()->user()?->can('contacts.view'))
                                    <a href="{{ route('contacts.show', ['contact' => $document->contact_id]) }}">{{ $document->contact->title }}</a>
                                @else
                                    {{ $document->contact?->title ?? '—' }}
                                @endif
                            </td>
                        @endunless
                        @unless($hiddenColumns['status'] ?? false)
                            <td><x-v65.status-badge :dot="true" :tone="in_array($document->status, ['posted', 'confirmed', 'approved'], true) ? 'success' : (in_array($document->status, ['cancelled', 'reversed'], true) ? 'danger' : (in_array($document->status, ['internal_review', 'customer_review', 'pending_approval'], true) ? 'warning' : 'neutral'))">{{ $statusLabels[$document->status] ?? $document->status }}</x-v65.status-badge></td>
                        @endunless
                        @unless($hiddenColumns['total'] ?? false)
                            <td class="align-end tabular">{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</td>
                        @endunless
                    </tr>
                @empty
                    <tr><td colspan="{{ collect(['number','date','contact','status','total'])->reject(fn ($column) => $hiddenColumns[$column] ?? false)->count() }}">
                        <x-v65.empty-state title="Kayıt bulunamadı.">
                            @if($createRoute)
                                <x-v65.button size="sm" :href="route($createRoute)">+ Yeni Kayıt</x-v65.button>
                            @endif
                        </x-v65.empty-state>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-v65.list-footer :paginator="$documents" :per-page="$perPage">
        <x-slot:columnControls>
            <x-v65.column-picker>
                @foreach(['number' => 'Numara', 'date' => 'Tarih', 'contact' => 'Cari', 'status' => 'Durum', 'total' => 'Tutar'] as $key => $label)
                    <label class="v65-column-picker-option" wire:key="column-pick-{{ $key }}">
                        <input type="checkbox"
                               @checked(!($hiddenColumns[$key] ?? false))
                               wire:click="toggleColumn('{{ $key }}')">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </x-v65.column-picker>
        </x-slot:columnControls>
    </x-v65.list-footer>
</div>
