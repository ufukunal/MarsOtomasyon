<div class="space-y-6 v65-list-page">
    @if($editingReturnDraft && $selectedReturnId)
        <x-v65.notice tone="info">İade taslağı #{{ $selectedReturnId }} düzenleniyor. Fatura, cari ve belge türü değiştirilemez; miktar, lokasyon, tarih ve notlar düzenlenebilir. Kesinleşmiş iade önce terslenmelidir.</x-v65.notice>
    @endif
    @if($errors->any())
        <x-v65.notice tone="danger">{{ $errors->first() }}</x-v65.notice>
    @endif

    <section class="panel stack v65-workspace-form">
        <label>İade Türü
            <select wire:model.live="type" @disabled($editingReturnDraft)>
                <option value="sales_return">Satış İadesi</option>
                <option value="purchase_return">Alış İadesi</option>
            </select>
        </label>

        <label>Kaynak Fatura
            <select wire:model.live="sourceInvoiceId" @disabled($editingReturnDraft)>
                <option value="">Seçin</option>
                @foreach($sourceInvoices as $invoice)
                    <option value="{{ $invoice->id }}">
                        {{ $invoice->number }} · {{ $invoice->contact?->title }} · {{ $invoice->grand_total }} {{ $invoice->currency }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>İade Tarihi <input type="date" wire:model="documentDate"></label>
        <label>Not <textarea wire:model="note"></textarea></label>

        @if($sourceInvoice)
            <div class="table-scroll v65-list-table"><table class="data-table">
                <thead><tr><th>Satır</th><th>Ürün/Hizmet</th><th>Fatura Miktarı</th><th>İade</th><th>Lokasyon</th></tr></thead>
                <tbody>
                @foreach($sourceInvoice->lines as $line)
                    <tr>
                        <td>{{ $line->line_no }}</td>
                        <td>{{ $line->description }}</td>
                        <td>{{ $line->quantity }}</td>
                        <td><input wire:model="quantities.{{ $line->id }}"></td>
                        <td>
                            @if($line->line_kind === 'stock')
                                <select wire:model="locationIds.{{ $line->id }}">
                                    <option value="">{{ $type === 'sales_return' ? 'Kabul lokasyonu' : 'Çıkış lokasyonu' }}</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                {{ $line->location?->name ?? '—' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>

            @if($editingReturnDraft)
                @can('returns.update')
                    <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">Düzenlemeleri Kaydet</button>
                @endcan
                <button type="button" class="button-secondary" wire:click="selectReturn({{ $selectedReturnId }})">Düzenlemeyi Kapat</button>
            @else
                @can('returns.create')
                    <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create">İade Taslağı Oluştur</button>
                @endcan
            @endif
        @endif
    </section>

    @if($selectedReturn)
        <section class="panel stack v65-workspace-form">
            <h2>{{ $selectedReturn->number ?? 'Taslak' }} · {{ \App\Support\Formatting\BusinessLabelFormatter::documentType($selectedReturn->document_type) }} · {{ \App\Support\Formatting\BusinessLabelFormatter::status($selectedReturn->status) }}</h2>
            <div>Cari: {{ $selectedReturn->contact?->title }}</div>
            <div>Tutar: {{ $selectedReturn->grand_total }} {{ $selectedReturn->currency }}</div>

            @if($selectedReturn->status === 'draft')
                @can('returns.update')
                    @unless($editingReturnDraft)
                        <button type="button" class="button-secondary" wire:click="editDraft({{ $selectedReturn->id }})">Düzenle</button>
                    @endunless
                    <button type="button" wire:click="post" wire:confirm="Son kaydedilen iade taslağı stok ve cari hareketi oluşturacak. Önce değişiklikleri kaydettiğinizden emin misiniz?" wire:loading.attr="disabled" wire:target="post" @disabled($editingReturnDraft)>Kesinleştir</button>
                @endcan
            @elseif($selectedReturn->status === 'posted')
                @can('returns.cancel')
                    <input type="date" wire:model="reversalDate">
                    <input wire:model="reversalReason" placeholder="Ters kayıt gerekçesi">
                    <button class="button-danger" type="button" wire:click="reverse" wire:confirm="Kesinleşmiş iade silinmez. Ters kayıtla stok ve cari hareketleri düzeltilir; ardından yeni iade düzenlenebilir. Devam edilsin mi?">Ters Kayıt ile Düzelt</button>
                @endcan
            @endif

            @if($selectedReturn->document_type === \App\Enums\DocumentType::SalesReturn && $selectedReturn->status === 'posted')
                <p>Finansal iade kesinleşmiştir. Stok karantina kararından bağımsızdır; kontrol için Karantina ekranını kullanın.</p>
            @endif
        </section>
    @endif

    <section class="panel stack">
        <x-v65.list-heading title="İade Geçmişi" />
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>No</th><th>Tür</th><th>Cari</th><th>Tarih</th><th class="align-end">Tutar</th><th>Durum</th><th>İşlem</th></tr></thead>
            <tbody>
            @foreach($returns as $return)
                <tr>
                    <td><button type="button" class="link-button v65-record-link" wire:click="selectReturn({{ $return->id }})">{{ $return->number ?? 'Taslak' }}</button></td>
                    <td>{{ $return->document_type === \App\Enums\DocumentType::SalesReturn ? 'Satış' : 'Alış' }}</td>
                    <td>@if($return->contact && auth()->user()?->can('contacts.view'))<a href="{{ route('contacts.show', ['contact' => $return->contact_id]) }}">{{ $return->contact->title }}</a>@else{{ $return->contact?->title }}@endif</td>
                    <td>{{ $return->document_date?->format('d.m.Y') }}</td>
                    <td class="align-end">{{ $return->grand_total }} {{ $return->currency }}</td>
                    <td>{{ \App\Support\Formatting\BusinessLabelFormatter::status($return->status) }}</td>
                    <td>
                        @if($return->status === 'draft' && auth()->user()?->can('returns.update'))
                            <button type="button" class="button-secondary" wire:click="editDraft({{ $return->id }})">Düzenle</button>
                        @else
                            <button type="button" class="button-secondary" wire:click="selectReturn({{ $return->id }})">
                                {{ $return->status === 'posted' ? 'İncele / Düzelt' : 'İncele' }}
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
</div>
