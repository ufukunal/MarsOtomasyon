<div class="space-y-5 panel v65-detail-screen">
    <h1>Proforma {{ $proforma->number }}</h1>
    <x-v65.notice tone="info">
        Proforma, onaylı teklif veya siparişten oluşturulan kayıtlı bir anlık görüntüdür.
        Değişiklikler için kaynağı açıp yeni teklif revizyonu veya sipariş düzenleme taslağı oluşturun;
        ardından yeni proforma düzenleyin. Önceki proforma ve ona bağlı faturalar değiştirilmez.
    </x-v65.notice>
    @if($originalSource && auth()->user()?->can($sourcePermission))
        <x-v65.button variant="secondary" size="sm"
            :href="route($sourceRoute, ['id' => $originalSource->id])">
            Düzenle / Yeni Proforma İçin Kaynağı Aç
        </x-v65.button>
    @endif
    <div>{{ $proforma->contact?->title }} · {{ $proforma->document_date->format('d.m.Y') }} · {{ $proforma->grand_total }} {{ $proforma->currency }}</div>

    <div class="table-scroll v65-grid-scroll"><table class="data-table">
        <thead><tr><th>Satır</th><th>Açıklama</th><th>Miktar</th><th>Fiyat</th><th>Toplam</th><th>Fatura Lokasyonu</th></tr></thead>
        <tbody>
            @foreach($proforma->lines as $line)
                <tr>
                    <td>{{ $line->line_no }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->quantity }}</td>
                    <td>{{ $line->unit_price }}</td>
                    <td>{{ $line->line_total }}</td>
                    <td>
                        @if($line->line_kind === 'stock')
                            <select wire:model="fallbackLocationIds.{{ $line->id }}">
                                <option value="">Kaynak rezervasyonu/lokasyonu kullan</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table></div>

    <label>Fatura tarihi <input type="date" wire:model="invoiceDate"></label>
    <button type="button" wire:click="createInvoice">Satış Faturası Oluştur</button>
</div>
