<div class="document-workspace stack">
    <div>
        <span class="page-category">Satış Yönetimi</span><h2>{{ $title }}</h2>
        @if($document)
            <div>{{ $document->number ?? 'Taslak' }} · {{ $document->status }}</div>
        @endif
    </div>

    @if(!$document || $document->status === 'draft')
        <form wire:submit="save" class="document-editor-form">
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

            <div>
                <label>KDV % <input wire:model="bulkVatRate"></label>
                <button class="button-secondary" type="button" wire:click="applyVatToAll">Tümüne KDV Uygula</button>
                <button class="button-secondary" type="button" wire:click="clearVat">KDV Temizle</button>
            </div>

            <div class="table-scroll document-lines"><table class="data-table">
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
                            <td><input wire:model="lines.{{ $index }}.quantity" inputmode="decimal" aria-label="Satır {{ $index + 1 }} miktar"></td>
                            <td>
                                <select wire:model="lines.{{ $index }}.location_id">
                                    <option value="">Seçin</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input wire:model="lines.{{ $index }}.unit_price" inputmode="decimal" aria-label="Satır {{ $index + 1 }} birim fiyat"></td>
                            <td><input wire:model="lines.{{ $index }}.line_discount_rate" inputmode="decimal" aria-label="Satır {{ $index + 1 }} iskonto"></td>
                            <td><input wire:model="lines.{{ $index }}.vat_rate" inputmode="decimal" aria-label="Satır {{ $index + 1 }} KDV"></td>
                            <td><button class="button-danger" type="button" wire:click="removeLine({{ $index }})">Sil</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>

            <button class="button-secondary" type="button" wire:click="addLine">Satır Ekle</button>
            <button type="submit" wire:loading.attr="disabled" wire:target="save">Kaydet</button>
        </form>
    @endif

    @if($document)
        <div class="document-actions panel v65-document-actions" role="group" aria-label="Belge işlemleri">
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
                <section class="v65-order-fulfillment" aria-label="Sipariş rezervasyon ve sevkiyat işlemleri">
                    <header class="v65-order-fulfillment-head">
                        <strong>Stok Rezervasyonu ve Sevkiyat</strong>
                        <small>Rezervasyon deposu ile sevk/fatura deposu birbirinden bağımsızdır.</small>
                    </header>
                    <div class="v65-order-fulfillment-lines">
                        @foreach($document->lines as $line)
                            <div class="v65-order-fulfillment-line" wire:key="order-fulfillment-{{ $line->id }}">
                                <div class="v65-order-fulfillment-item">
                                    <strong>{{ $line->line_no }} · {{ $line->description ?? 'Sipariş satırı' }}</strong>
                                    <span>Sipariş miktarı: {{ $line->quantity }} · Aktif rezervasyon (temel birim): <b>{{ $reservedQuantities[$line->id] ?? '0.000' }}</b></span>
                                </div>
                                <div class="v65-order-fulfillment-inputs">
                                    <label class="field">
                                        <span class="field-label">Sevk / Fatura Miktarı</span>
                                        <input wire:model="fulfillmentQuantities.{{ $line->id }}" inputmode="decimal" aria-label="Satır {{ $line->line_no }} sevk veya fatura miktarı">
                                    </label>
                                    <label class="field">
                                        <span class="field-label">Sevk / Fatura Deposu</span>
                                        <select wire:model="fulfillmentLocationIds.{{ $line->id }}" aria-label="Satır {{ $line->line_no }} sevk/fatura deposu">
                                            <option value="">Depo seçin</option>
                                            @foreach($locations as $location)
                                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    @if($line->line_kind === 'stock')
                                        <label class="field">
                                            <span class="field-label">Rezervasyon Deposu</span>
                                            <select wire:model="reservationLocationIds.{{ $line->id }}" aria-label="Satır {{ $line->line_no }} rezervasyon deposu">
                                                <option value="">Depo seçin</option>
                                                @foreach($locations as $location)
                                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($reservationNotice !== '')
                        <div class="v65-reservation-notice {{ $reservationNoticeLevel === 'success' ? 'is-success' : 'is-warning' }}" role="status" aria-live="polite">{{ $reservationNotice }}</div>
                    @endif
                    <div class="v65-order-fulfillment-actions">
                        @can('reservations.create')
                            <button type="button" wire:click="reserve" wire:loading.attr="disabled" wire:target="reserve">Rezerv Et</button>
                        @else
                            <small class="v65-reservation-permission">Stok rezervasyonu oluşturma yetkiniz yok.</small>
                        @endcan
                        <button type="button" wire:click="createDispatch" wire:loading.attr="disabled" wire:target="createDispatch">İrsaliye Oluştur</button>
                        <button type="button" wire:click="createInvoice" wire:loading.attr="disabled" wire:target="createInvoice">Fatura Oluştur</button>
                        <button type="button" wire:click="issueProforma" wire:loading.attr="disabled" wire:target="issueProforma">Proforma Oluştur</button>
                        <button class="button-danger" type="button" wire:click="cancelRemaining" wire:loading.attr="disabled" wire:target="cancelRemaining" wire:confirm="Siparişin kalan miktarını iptal etmek istediğinize emin misiniz?">Kalanı İptal Et</button>
                    </div>
                </section>
            @elseif(method_exists($this, 'issueProforma') && in_array($document->status, ['approved','confirmed']))
                <button type="button" wire:click="issueProforma">Proforma Oluştur</button>
            @endif
            @if(method_exists($this, 'post') && $document->status === 'draft')
                @if($this instanceof \App\Livewire\Sales\SalesInvoiceEditor)
                    @php
                        $invoiceRisk = $this->invoiceRiskProjection();
                    @endphp
                    @if($invoiceRisk?->knownLimitExceeded)
                        <div class="alert alert-warning" role="alert">
                            <strong>Cari risk limiti aşılıyor.</strong>
                            Mevcut cari net bakiyesi: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->currentBalance) }} ₺;
                            fatura: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->orderTotal) }} ₺;
                            tahmini risk: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->knownExposure) }} ₺;
                            limit: {{ \App\Support\Formatting\TrFormatter::money($invoiceRisk->riskLimit) }} ₺.
                            <label><input type="checkbox" wire:model="riskAccepted"> Risk limitinin aşıldığını kabul ediyorum.</label>
                        </div>
                    @elseif($document->currency !== 'TRY')
                        <div class="alert alert-warning" role="status">
                            Fatura TRY dışında bir para birimindedir. TRY risk limitiyle döviz tutarı doğrudan karşılaştırılamaz; risk ayrıca kontrol edilmelidir.
                        </div>
                    @endif
                @endif
                <button type="button" wire:click="post">Kesinleştir</button>
            @endif
            @if(method_exists($this, 'reverse') && $document->status === 'posted')
                <label class="field"><span class="field-label">Ters Kayıt Tarihi</span><input type="date" wire:model="reversalDate"></label>
                <label class="field"><span class="field-label">Ters Kayıt Gerekçesi</span><input wire:model="reversalReason"></label>
                <button class="button-danger" type="button" wire:click="reverse">Ters Kayıt</button>
            @endif
        </div>
    @endif
</div>
