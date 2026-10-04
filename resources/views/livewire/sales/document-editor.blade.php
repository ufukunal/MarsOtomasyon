<div class="space-y-6">
    <div>
        <h1>{{ $title }}</h1>
        @if($document)
            <div>{{ $document->number ?? 'Taslak' }} · {{ $document->status }}</div>
        @endif
    </div>

    @if(!$document || $document->status === 'draft')
        <form wire:submit="save" class="space-y-4">
            <label>Cari
                <select wire:model="contactId">
                    <option value="">Seçin</option>
                    @foreach($contacts as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                    @endforeach
                </select>
            </label>

            <label>Tarih <input type="date" wire:model="documentDate"></label>
            <label>Vade <input type="date" wire:model="dueDate"></label>
            <label>Geçerlilik <input type="date" wire:model="validUntil"></label>
            <label>Belge İskonto % <input wire:model="discountRate"></label>
            <label>Not <textarea wire:model="notes"></textarea></label>

            <table>
                <thead>
                    <tr>
                        <th>Tür</th><th>Ürün</th><th>Birim</th><th>Miktar</th><th>Lokasyon</th>
                        <th>Fiyat</th><th>İsk. %</th><th>KDV %</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $index => $line)
                        <tr wire:key="line-{{ $index }}">
                            <td>
                                <select wire:model="lines.{{ $index }}.line_kind">
                                    <option value="stock">Stok</option>
                                    <option value="service">Hizmet</option>
                                </select>
                            </td>
                            <td>
                                <select wire:model="lines.{{ $index }}.product_id">
                                    <option value="">Seçin</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select wire:model="lines.{{ $index }}.unit_id">
                                    <option value="">Seçin</option>
                                    @foreach($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input wire:model="lines.{{ $index }}.quantity"></td>
                            <td>
                                <select wire:model="lines.{{ $index }}.location_id">
                                    <option value="">Seçin</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input wire:model="lines.{{ $index }}.unit_price"></td>
                            <td><input wire:model="lines.{{ $index }}.line_discount_rate"></td>
                            <td><input wire:model="lines.{{ $index }}.vat_rate"></td>
                            <td><button type="button" wire:click="removeLine({{ $index }})">Sil</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <button type="button" wire:click="addLine">Satır Ekle</button>
            <button type="submit">Kaydet</button>
        </form>
    @endif

    @if($document)
        <div class="space-y-3">
            @if(method_exists($this, 'internalReview') && $document->status === 'draft')
                <button type="button" wire:click="internalReview">İç Onaya Gönder</button>
                <button type="button" wire:click="customerReview">Müşteri İncelemesine Gönder</button>
            @endif
            @if(method_exists($this, 'approve') && in_array($document->status, ['internal_review','customer_review']))
                <button type="button" wire:click="approve">Onayla</button>
            @endif
            @if(method_exists($this, 'revision') && $document->number)
                <button type="button" wire:click="revision">Revizyon Oluştur</button>
            @endif
            @if(method_exists($this, 'toOrder') && $document->status === 'approved')
                <button type="button" wire:click="toOrder">Sipariş Oluştur</button>
            @endif
            @if(method_exists($this, 'confirm') && $document->status === 'draft')
                <label><input type="checkbox" wire:model="riskAccepted"> Risk uyarısını kabul ediyorum</label>
                <button type="button" wire:click="confirm">Siparişi Onayla</button>
            @endif
            @if(method_exists($this, 'reserve') && $document->status === 'confirmed')
                @foreach($document->lines as $line)
                    <div>
                        <strong>{{ $line->line_no }} · {{ $line->description }}</strong>
                        <input wire:model="fulfillmentQuantities.{{ $line->id }}" aria-label="Miktar">
                        <select wire:model="fulfillmentLocationIds.{{ $line->id }}">
                            <option value="">Sevk/Fatura Lokasyonu</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model="reservationLocationIds.{{ $line->id }}">
                            <option value="">Rezervasyon Lokasyonu</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <button type="button" wire:click="reserve">Rezerv Et</button>
                <button type="button" wire:click="createDispatch">İrsaliye Oluştur</button>
                <button type="button" wire:click="createInvoice">Fatura Oluştur</button>
                <button type="button" wire:click="cancelRemaining">Kalanı İptal Et</button>
            @endif
            @if(method_exists($this, 'issueProforma') && in_array($document->status, ['approved','confirmed']))
                <button type="button" wire:click="issueProforma">Proforma Oluştur</button>
            @endif
            @if(method_exists($this, 'post') && $document->status === 'draft')
                <button type="button" wire:click="post">Kesinleştir</button>
            @endif
            @if(method_exists($this, 'reverse') && $document->status === 'posted')
                <input type="date" wire:model="reversalDate">
                <input wire:model="reversalReason" placeholder="Ters kayıt gerekçesi">
                <button type="button" wire:click="reverse">Ters Kayıt</button>
            @endif
        </div>
    @endif
</div>
