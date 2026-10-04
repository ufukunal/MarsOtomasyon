<div class="space-y-6">
    <h1>İade Merkezi</h1>

    <section class="space-y-3">
        <label>İade Türü
            <select wire:model.live="type">
                <option value="sales_return">Satış İadesi</option>
                <option value="purchase_return">Alış İadesi</option>
            </select>
        </label>

        <label>Kaynak Fatura
            <select wire:model.live="sourceInvoiceId">
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
            <table>
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
            </table>

            @can('returns.create')
                <button type="button" wire:click="create">İade Taslağı Oluştur</button>
            @endcan
        @endif
    </section>

    @if($selectedReturn)
        <section class="space-y-3">
            <h2>{{ $selectedReturn->number ?? 'Taslak' }} · {{ $selectedReturn->document_type->value }} · {{ $selectedReturn->status }}</h2>
            <div>Cari: {{ $selectedReturn->contact?->title }}</div>
            <div>Tutar: {{ $selectedReturn->grand_total }} {{ $selectedReturn->currency }}</div>

            @if($selectedReturn->status === 'draft')
                @can('returns.update')
                    <button type="button" wire:click="post">Kesinleştir</button>
                @endcan
            @elseif($selectedReturn->status === 'posted')
                @can('returns.cancel')
                    <input type="date" wire:model="reversalDate">
                    <input wire:model="reversalReason" placeholder="Ters kayıt gerekçesi">
                    <button type="button" wire:click="reverse">Ters Kayıt</button>
                @endcan
            @endif

            @if($selectedReturn->document_type === AppEnumsDocumentType::SalesReturn && $selectedReturn->status === 'posted')
                <p>Finansal iade kesinleşmiştir. Stok karantina kararından bağımsızdır; kontrol için Karantina ekranını kullanın.</p>
            @endif
        </section>
    @endif

    <section>
        <h2>İade Geçmişi</h2>
        <table>
            <thead><tr><th>No</th><th>Tür</th><th>Cari</th><th>Tarih</th><th>Tutar</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            @foreach($returns as $return)
                <tr>
                    <td>{{ $return->number ?? 'Taslak' }}</td>
                    <td>{{ $return->document_type === AppEnumsDocumentType::SalesReturn ? 'Satış' : 'Alış' }}</td>
                    <td>{{ $return->contact?->title }}</td>
                    <td>{{ $return->document_date?->format('d.m.Y') }}</td>
                    <td>{{ $return->grand_total }} {{ $return->currency }}</td>
                    <td>{{ $return->status }}</td>
                    <td><button type="button" wire:click="selectReturn({{ $return->id }})">Aç</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>
