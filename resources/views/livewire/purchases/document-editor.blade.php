<div class="document-workspace stack">
    @php($isGoodsReceipt = $document?->document_type === \App\Enums\DocumentType::GoodsReceipt)

    <div>
        <span class="page-category">Satınalma Yönetimi</span><h2>{{ $title }}</h2>
        @if($document)
            <div>{{ $document->number ?? 'Taslak' }} · {{ $document->status }}</div>
        @endif
    </div>

    @if(!$document || $document->status === 'draft')
        <form wire:submit="save" class="document-editor-form">
            <label>Tedarikçi
                <select wire:model="contactId">
                    <option value="">Seçin</option>
                    @foreach($contacts as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                    @endforeach
                </select>
            </label>
            <label>Tarih <input type="date" wire:model="documentDate"></label>
            @if(!$isGoodsReceipt)
                <label>Vade <input type="date" wire:model="dueDate"></label>
                <label>Para Birimi <input wire:model="currency" maxlength="3"></label>
                <label>Kur <input wire:model="exchangeRate"></label>
                <label>Belge İskonto % <input wire:model="discountRate"></label>
            @else
                <div>{{ $currency }} · kur {{ $exchangeRate }}</div>
            @endif
            <label>Not <textarea wire:model="notes"></textarea></label>

            @if(!$isGoodsReceipt)
                <div>
                    <label>KDV % <input wire:model="bulkVatRate"></label>
                    <button class="button-secondary" type="button" wire:click="applyVatToAll">Tümüne KDV Uygula</button>
                    <button class="button-secondary" type="button" wire:click="clearVat">KDV Temizle</button>
                </div>
            @endif

            <div class="table-scroll document-lines"><table class="data-table">
                <thead>
                <tr>
                    <th>Tür</th><th>Ürün</th><th>Birim</th><th>Miktar</th><th>Lokasyon</th>
                    @if(!$isGoodsReceipt)
                        <th>Fiyat</th><th>İsk. %</th><th>KDV %</th><th></th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @foreach($lines as $index => $line)
                    <tr wire:key="purchase-line-{{ $index }}">
                        <td>
                            @if($isGoodsReceipt)
                                {{ $line['line_kind'] === 'stock' ? 'Stok' : 'Hizmet' }}
                            @else
                                <select wire:model="lines.{{ $index }}.line_kind">
                                    <option value="stock">Stok</option>
                                    <option value="service">Hizmet</option>
                                </select>
                            @endif
                        </td>
                        <td>
                            @if($isGoodsReceipt)
                                {{ $line['description'] ?? 'Kaynak satır' }}
                            @else
                                <select wire:model="lines.{{ $index }}.product_id">
                                    <option value="">Seçin</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        <td>
                            @if($isGoodsReceipt)
                                {{ $line['unit_id'] ?? '—' }}
                            @else
                                <select wire:model="lines.{{ $index }}.unit_id">
                                    <option value="">Seçin</option>
                                    @foreach($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                    @endforeach
                                </select>
                            @endif
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
                        @if(!$isGoodsReceipt)
                            <td><input wire:model="lines.{{ $index }}.unit_price" inputmode="decimal" aria-label="Satır {{ $index + 1 }} birim fiyat"></td>
                            <td><input wire:model="lines.{{ $index }}.line_discount_rate" inputmode="decimal" aria-label="Satır {{ $index + 1 }} iskonto"></td>
                            <td><input wire:model="lines.{{ $index }}.vat_rate" inputmode="decimal" aria-label="Satır {{ $index + 1 }} KDV"></td>
                            <td><button class="button-danger" type="button" wire:click="removeLine({{ $index }})">Sil</button></td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>

            @if(!$isGoodsReceipt)
                <button class="button-secondary" type="button" wire:click="addLine">Satır Ekle</button>
            @endif
            <button type="submit" wire:loading.attr="disabled" wire:target="save">Kaydet</button>
        </form>
    @endif

    @if($document)
        <div class="document-actions panel">
            @if(method_exists($this, 'submitApproval') && $document->status === 'draft')
                <button type="button" wire:click="submitApproval">Onaya Gönder</button>
            @endif

            @if(method_exists($this, 'approve') && $document->status === 'pending_approval')
                @can('purchase_orders.approve')
                    <button type="button" wire:click="approve">Onayla</button>
                @endcan
            @endif

            @if(method_exists($this, 'sendToSupplier') && $document->status === 'approved')
                <button type="button" wire:click="sendToSupplier">Tedarikçiye Gönder</button>
            @endif

            @if(method_exists($this, 'createGoodsReceipt') && in_array($document->status, ['approved', 'sent']))
                <label>Mal Kabul Tarihi <input type="date" wire:model="receiptDate"></label>
                @foreach($document->lines as $line)
                    <div>
                        <strong>{{ $line->line_no }} · {{ $line->description }}</strong>
                        <input wire:model="receiptQuantities.{{ $line->id }}" aria-label="Mal kabul miktarı">
                        @if($line->line_kind === 'stock')
                            <select wire:model="receiptLocationIds.{{ $line->id }}">
                                <option value="">Depo / Lokasyon</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endforeach
                <button type="button" wire:click="createGoodsReceipt">Mal Kabul Oluştur</button>
            @endif

            @if(method_exists($this, 'post') && $document->status === 'draft')
                @if(property_exists($this, 'deviationAccepted'))
                    <label>
                        <input type="checkbox" wire:model="deviationAccepted">
                        ±%25 alış fiyat sapması varsa uyarıyı kabul ediyorum
                    </label>
                @endif
                <button type="button" wire:click="post">Kesinleştir</button>
            @endif

            @if(method_exists($this, 'createInvoice') && $document->status === 'posted')
                <label>Fatura Tarihi <input type="date" wire:model="invoiceDate"></label>
                <label>Para Birimi <input wire:model="invoiceCurrency" maxlength="3"></label>
                <label>Kur <input wire:model="invoiceExchangeRate"></label>
                @foreach($document->lines as $line)
                    <div>
                        <strong>{{ $line->line_no }} · {{ $line->description }}</strong>
                        <input wire:model="invoiceQuantities.{{ $line->id }}" aria-label="Fatura miktarı">
                    </div>
                @endforeach
                <button type="button" wire:click="createInvoice">Alış Faturası Oluştur</button>
            @endif

            @if(method_exists($this, 'reverse') && $document->status === 'posted')
                <label class="field"><span class="field-label">Ters Kayıt Tarihi</span><input type="date" wire:model="reversalDate"></label>
                <label class="field"><span class="field-label">Ters Kayıt Gerekçesi</span><input wire:model="reversalReason"></label>
                <button class="button-danger" type="button" wire:click="reverse">Ters Kayıt</button>
            @endif

            @if(isset($purchaseMatches) && $purchaseMatches->isNotEmpty())
                <h2>Üçlü Eşleştirme</h2>
                <table>
                    <thead>
                    <tr>
                        <th>Fatura Satırı</th>
                        <th>Sipariş Fiyatı</th>
                        <th>Fatura Fiyatı</th>
                        <th>Fiyat Sapması %</th>
                        <th>TRY Maliyet</th>
                        <th>Yeni Ort.</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($purchaseMatches as $match)
                        <tr>
                            <td>{{ $match->supplier_invoice_line_id }}</td>
                            <td>{{ $match->order_unit_price }}</td>
                            <td>{{ $match->invoice_unit_price }}</td>
                            <td>{{ $match->price_variance_rate }}</td>
                            <td>{{ $match->cost_unit_try ?? '—' }}</td>
                            <td>{{ $match->new_moving_average ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</div>
