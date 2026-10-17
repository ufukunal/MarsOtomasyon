<div class="stack v65-list-page v65-document-history-detail">
    <header class="v65-page-heading">
        <div>
            <h1>{{ $entry['label'] }} — Kayıt Geçmişi</h1>
            <p>{{ $document->number ?: 'Taslak #'.$document->id }} · {{ \App\Support\Formatting\BusinessLabelFormatter::status($document->status) }}</p>
        </div>
        <div class="v65-document-history-actions">
            <x-v65.button variant="secondary" size="sm" :href="route('documents.edit-center')">
                Belge Düzenleme Merkezi
            </x-v65.button>
            @if($editUrl)
                <x-v65.button size="sm" :href="$editUrl">Düzenleme / Düzeltme Ekranını Aç</x-v65.button>
            @else
                <x-v65.button variant="secondary" size="sm" :href="route($entry['route'])">
                    İlgili Modüle Git
                </x-v65.button>
            @endif
        </div>
    </header>

    <x-v65.notice tone="info">
        Bu ekran yalnızca seçilen belgeyi ve işlem geçmişini gösterir.
        Mali veya stok hareketlerini yerinde değiştirmez. Kesinleşmiş kayıtların
        düzeltilmesi için yetkili ters kayıt veya revizyon iş akışı gerekir.
    </x-v65.notice>

    <section class="panel" aria-labelledby="document-history-details">
        <h2 id="document-history-details">Belge Bilgileri</h2>
        <dl class="v65-history-key-values">
            <div><dt>Belge Türü</dt><dd>{{ $entry['label'] }}</dd></div>
            <div><dt>Belge No</dt><dd>{{ $document->number ?: 'Taslak #'.$document->id }}</dd></div>
            <div><dt>Belge Tarihi</dt><dd>{{ $document->document_date?->format('d.m.Y') ?: '—' }}</dd></div>
            <div><dt>Durum</dt><dd>{{ \App\Support\Formatting\BusinessLabelFormatter::status($document->status) }}</dd></div>
            <div><dt>Revizyon</dt><dd>{{ $document->revision_no }}</dd></div>
            @if($showContact)
                <div><dt>Cari</dt><dd>{{ $document->contact?->title ?: '—' }}</dd></div>
            @endif
            <div><dt>Para Birimi</dt><dd>{{ $document->currency }}</dd></div>
            <div><dt>Genel Toplam</dt><dd>{{ \App\Support\Formatting\TrFormatter::money((string) $document->grand_total) }} {{ $document->currency }}</dd></div>
        </dl>
        @if($document->notes)
            <p class="v65-document-history-notes"><strong>Açıklama:</strong> {{ $document->notes }}</p>
        @endif
    </section>

    @if($document->lines->isNotEmpty())
        <section class="panel" aria-labelledby="document-history-lines">
            <h2 id="document-history-lines">Kayıtlı Kalemler</h2>
            <div class="table-scroll v65-grid-scroll">
                <table class="data-table">
                    <thead><tr>
                        <th>#</th><th>Ürün / Hizmet</th><th class="align-end">Miktar</th>
                        <th class="align-end">Birim Fiyatı</th><th class="align-end">KDV %</th>
                        <th class="align-end">Satır Tutarı</th>
                    </tr></thead>
                    <tbody>
                        @foreach($document->lines as $line)
                            <tr wire:key="history-line-{{ $line->id }}">
                                <td>{{ $line->line_no }}</td>
                                <td>{{ $line->description }}</td>
                                <td class="align-end">{{ $line->quantity }}</td>
                                <td class="align-end">{{ \App\Support\Formatting\TrFormatter::money((string) $line->unit_price) }}</td>
                                <td class="align-end">{{ $line->vat_rate }}</td>
                                <td class="align-end">{{ \App\Support\Formatting\TrFormatter::money((string) $line->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="panel" aria-labelledby="document-history-relations">
        <h2 id="document-history-relations">Bağlı Belgeler / Revizyonlar / Ters Kayıtlar</h2>
        @if($linkedDocuments->isNotEmpty())
            <div class="table-scroll v65-grid-scroll">
                <table class="data-table">
                    <thead><tr><th>İlişki</th><th>Bağlı Belge</th><th>Durum</th><th>İşlem</th></tr></thead>
                    <tbody>
                        @foreach($linkedDocuments as $index => $link)
                            <tr wire:key="history-related-{{ $index }}">
                                <td>{{ $link['relation'] }}</td>
                                <td>{{ $link['other']->number ?: 'Taslak #'.$link['other']->id }}</td>
                                <td>{{ \App\Support\Formatting\BusinessLabelFormatter::status($link['other']->status) }}</td>
                                <td>
                                    <x-v65.button variant="secondary" size="xs"
                                        :href="route('documents.history', ['id' => $link['other']->id])">
                                        Geçmişi Aç
                                    </x-v65.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p>Görüntüleyebileceğiniz bağlı belge bulunmuyor.</p>
        @endif
    </section>
</div>
