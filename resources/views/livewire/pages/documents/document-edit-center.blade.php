<div class="space-y-5 v65-list-page">
    <header class="v65-page-heading">
        <div>
            <h1>Belge Düzenleme Merkezi</h1>
            <p>Satış, alış, finans ve iade belgelerinin tamamına durumuna uygun düzenleme veya düzeltme ekranından erişin.</p>
        </div>
    </header>
    <x-v65.notice tone="info">
        Taslaklar doğrudan düzenlenir. Kesinleşmiş belgelerde eski cari/stock kayıtları yerinde değiştirilmez:
        ilgili ekranın ters kayıt veya revizyon işlemi kullanılır. Bu sayfa yalnız kayıtları bulur ve yönlendirir.
    </x-v65.notice>
    <section class="panel v65-document-edit-search" aria-label="Belge arama">
        <div class="v65-edit-filters">
            <label class="field">
                <span>Belge No / Cari Kodu / Unvan</span>
                <input type="search" wire:model.live.debounce.350ms="search" placeholder="Belge veya cari ara" maxlength="90" autocomplete="off">
            </label>
            <label class="field">
                <span>Belge Türü</span>
                <select wire:model.live="type">
                    <option value="">Yetkili olduğum tüm belgeler</option>
                    @foreach($availableTypes as $key => $entry)
                        <option value="{{ $key }}">{{ $entry['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Durum</span>
                <select wire:model.live="status">
                    <option value="">Tüm durumlar</option>
                    @foreach($statusOptions as $name)
                        <option value="{{ $name }}">{{ \App\Support\Formatting\BusinessLabelFormatter::status($name) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>
    <section class="panel" aria-label="Belge listesi">
        <div class="v65-doc-table-scroll">
            <table class="data-table">
                <thead>
                    <tr><th>Belge No</th><th>Tür</th><th>Cari</th><th>Tarih</th>
                        <th>Durum</th><th class="align-end">Tutar</th><th>İşlem</th></tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        @php $entry = $availableTypes[$document->document_type->value]; @endphp
                        <tr wire:key="doc-edit-{{ $document->id }}">
                            <td>{{ $document->number ?: 'Taslak #'.$document->id }}</td>
                            <td>{{ $entry['label'] }}</td>
                            <td>{{ $document->contact?->title ?: '—' }}</td>
                            <td>{{ $document->document_date?->format('d.m.Y') }}</td>
                            <td>
                                <x-v65.status-badge>{{ \App\Support\Formatting\BusinessLabelFormatter::status($document->status) }}</x-v65.status-badge>
                            </td>
                            <td class="align-end">{{ \App\Support\Formatting\TrFormatter::money((string) $document->grand_total) }} {{ $document->currency }}</td>
                            <td>
                                <a class="v65-btn v65-btn--secondary v65-btn--sm"
                                    href="{{ \App\Support\Documents\DocumentEditCatalog::url($document, $entry) }}">
                                    {{ \App\Support\Documents\DocumentEditCatalog::actionLabel($document, $entry) }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Filtreye uygun belge bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="v65-table-pagination">{{ $documents->links() }}</div>
    </section>

    @if($otherModules !== [])
        <section class="panel" aria-label="Diğer belge ve fiş türleri">
            <header class="v65-reference-info-head">
                <strong>Diğer Belge, Fiş ve Evraklar</strong>
                <small>Bu kayıtlar farklı veri modellerindedir; kendi düzenleme ekranları kullanılır.</small>
            </header>
            <div class="v65-doc-table-scroll">
                <table class="data-table">
                    <thead><tr><th>İşlem Ailesi</th><th>Düzenleme / Düzeltme Yöntemi</th><th>Ekran</th></tr></thead>
                    <tbody>
                        @foreach($otherModules as $module)
                            <tr>
                                <td>{{ $module['label'] }}</td>
                                <td>{{ $module['note'] }}</td>
                                <td>
                                    <a class="v65-btn v65-btn--secondary v65-btn--sm"
                                       href="{{ route($module['route']) }}">Düzenleme Ekranını Aç</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
