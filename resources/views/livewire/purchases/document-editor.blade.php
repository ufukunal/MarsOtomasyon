<div class="document-workspace stack">
    @php
        $isGoodsReceipt = $document?->document_type === \App\Enums\DocumentType::GoodsReceipt;
    @endphp

    <x-v65.document-heading :document="$document" :title="$title" section="Satınalma Yönetimi" />
    @if($errors->any())
        <x-v65.notice tone="danger" title="Belge kontrolü">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-v65.notice>
    @endif
    @if($documentDirty)
        <x-v65.notice tone="warning">Kaydedilmemiş değişiklikler var. Belge işlemine devam etmeden önce Kaydet'i kullanın.</x-v65.notice>
    @endif

    @if(!$document || $document->status === 'draft')
        <form wire:submit="save" class="document-editor-form v65-doc-editor">
            <x-v65.select label="Tedarikçi" wire:model="contactId" placeholder="Tedarikçi seçin" class="v65-doc-contact">
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </x-v65.select>
            <label class="field">Belge Tarihi <input type="date" wire:model="documentDate"></label>
            @if(!$isGoodsReceipt)
                <label class="field">Vade <input type="date" wire:model="dueDate"></label>
                <label class="field">Para Birimi <input wire:model="currency" maxlength="3"></label>
                <label class="field">Kur <input wire:model="exchangeRate" inputmode="decimal" data-tr-decimal></label>
                <label class="field">Belge İskonto % <input wire:model="discountRate" inputmode="decimal" data-tr-decimal></label>
            @else
                <div class="v65-doc-static-field"><strong>Para Birimi / Kur</strong><span>{{ $currency }} · {{ $exchangeRate }}</span></div>
            @endif
            <label class="field v65-doc-notes">Not <textarea wire:model="notes" rows="3"></textarea></label>

            @if(!$isGoodsReceipt)
                <div class="v65-doc-vat-toolbar" role="group" aria-label="Satırlara toplu KDV uygula">
                    <label class="field">Toplu KDV % <input wire:model="bulkVatRate" inputmode="decimal" data-tr-decimal></label>
                    <x-v65.button variant="secondary" size="sm" wire:click="applyVatToAll">Tüm Satırlara Uygula</x-v65.button>
                    <x-v65.button variant="secondary" size="sm" wire:click="clearVat">KDV'yi Temizle</x-v65.button>
                </div>
            @endif

            <section class="v65-doc-lines" aria-label="Belge kalemleri">
                <div class="v65-doc-lines-head">
                    <strong>Belge Kalemleri</strong>
                    <small>Fiyat ve vergi toplamları kaydedildiğinde güncellenir.</small>
                </div>
                <div class="table-scroll document-lines v65-doc-table-scroll"><table class="data-table v65-doc-table">
                <thead>
                <tr>
                    <th scope="col">#</th><th scope="col">Tür</th><th scope="col">Ürün / Açıklama</th><th scope="col">Birim</th><th scope="col" class="align-end">Miktar</th><th scope="col">Lokasyon</th>
                    @unless($isGoodsReceipt)
                        <th scope="col" class="align-end">Fiyat</th><th scope="col" class="align-end">İsk. %</th><th scope="col" class="align-end">KDV %</th>
                    @endunless
                    <th scope="col" class="align-end">Satır Net</th>
                    @unless($isGoodsReceipt)<th scope="col">İşlem</th>@endunless
                </tr>
                </thead>
                <tbody>
                @foreach($lines as $index => $line)
                    <tr wire:key="purchase-line-{{ $line['row_key'] ?? $index }}">
                        <td>{{ $index + 1 }}</td>
                        <td>
                            @if($isGoodsReceipt)
                                {{ $line['line_kind'] === 'stock' ? 'Stok' : 'Hizmet' }}
                            @else
                                <select wire:model.live="lines.{{ $index }}.line_kind" aria-label="Satır {{ $index + 1 }} tür">
                                    <option value="stock">Stok</option>
                                    <option value="service">Hizmet</option>
                                </select>
                            @endif
                        </td>
                        <td class="v65-doc-product-cell">
                            @if($isGoodsReceipt)
                                {{ $line['description'] ?? 'Kaynak satır' }}
                            @elseif(($line['line_kind'] ?? 'stock') === 'service')
                                <input wire:model="lines.{{ $index }}.description" placeholder="Hizmet açıklaması" aria-label="Satır {{ $index + 1 }} hizmet açıklaması">
                            @else
                                <select wire:model="lines.{{ $index }}.product_id" aria-label="Satır {{ $index + 1 }} ürün">
                                    <option value="">Ürün seçin</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                                    @endforeach
                                </select>
                                <input class="v65-doc-line-description" wire:model="lines.{{ $index }}.description" placeholder="Satır açıklaması (isteğe bağlı)" aria-label="Satır {{ $index + 1 }} açıklama">
                            @endif
                        </td>
                        <td>
                            @if($isGoodsReceipt)
                                {{ $units->firstWhere('id', (int) ($line['unit_id'] ?? 0))?->code ?? '—' }}
                            @elseif(($line['line_kind'] ?? 'stock') === 'service')
                                <span class="v65-doc-muted">—</span>
                            @else
                                <select wire:model="lines.{{ $index }}.unit_id" aria-label="Satır {{ $index + 1 }} birim">
                                    <option value="">Birim</option>
                                    @foreach($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        <td class="align-end"><input wire:model="lines.{{ $index }}.quantity" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} miktar"></td>
                        <td>
                            @if(($line['line_kind'] ?? 'stock') === 'service')
                                <span class="v65-doc-muted">—</span>
                            @else
                                <select wire:model="lines.{{ $index }}.location_id" aria-label="Satır {{ $index + 1 }} depo">
                                    <option value="">Depo seçin</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        @if(!$isGoodsReceipt)
                            <td class="align-end"><input wire:model="lines.{{ $index }}.unit_price" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} birim fiyat"></td>
                            <td class="align-end"><input wire:model="lines.{{ $index }}.line_discount_rate" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} iskonto"></td>
                            <td class="align-end"><input wire:model="lines.{{ $index }}.vat_rate" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} KDV"></td>
                        @endif
                        <td class="align-end v65-doc-line-net">@if(!$documentDirty && isset($line['line_total'])){{ \App\Support\Formatting\TrFormatter::money((string) $line['line_total']) }}@else—@endif</td>
                        @unless($isGoodsReceipt)
                            <td><x-v65.button variant="danger" size="xs" wire:click="removeLine({{ $index }})" aria-label="Satır {{ $index + 1 }} sil">Sil</x-v65.button></td>
                        @endunless
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            </section>

            <x-v65.form-actions align="between" class="v65-doc-form-actions">
                @unless($isGoodsReceipt)<x-v65.button variant="secondary" wire:click="addLine">+ Satır Ekle</x-v65.button>@endunless
                <x-v65.button type="submit" wire:loading.attr="disabled" wire:target="save">Kaydet</x-v65.button>
            </x-v65.form-actions>
        </form>
    @elseif($document)
        <x-v65.document-readonly :document="$document" />
    @endif

    <x-v65.document-summary :document="$document" />

    @if($document)
        <section class="document-actions panel v65-document-actions v65-doc-workflow" role="group" aria-label="Belge işlemleri">
            <h3 class="v65-doc-workflow-title">Belge İşlemleri</h3>
            @if(method_exists($this, 'submitApproval') && $document->status === 'draft')
                <x-v65.button variant="secondary" wire:click="submitApproval" wire:loading.attr="disabled" wire:target="submitApproval">Onaya Gönder</x-v65.button>
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
                <label class="field">Mal Kabul Tarihi <input type="date" wire:model="receiptDate"></label>
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
                <x-v65.button wire:click="createGoodsReceipt" wire:loading.attr="disabled" wire:target="createGoodsReceipt">Mal Kabul Oluştur</x-v65.button>
            @endif

            @if(method_exists($this, 'post') && $document->status === 'draft')
                @if(property_exists($this, 'deviationAccepted'))
                    <label>
                        <input type="checkbox" wire:model="deviationAccepted">
                        ±%25 alış fiyat sapması varsa uyarıyı kabul ediyorum
                    </label>
                @endif
                <x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post" wire:confirm="Son kaydedilen belgeyi kesinleştirmek istiyor musunuz?">Kesinleştir</x-v65.button>
            @endif

            @if(method_exists($this, 'createInvoice') && $document->status === 'posted')
                <label class="field">Fatura Tarihi <input type="date" wire:model="invoiceDate"></label>
                <label class="field">Para Birimi <input wire:model="invoiceCurrency" maxlength="3"></label>
                <label class="field">Kur <input wire:model="invoiceExchangeRate" data-tr-decimal inputmode="decimal"></label>
                @foreach($document->lines as $line)
                    <div>
                        <strong>{{ $line->line_no }} · {{ $line->description }}</strong>
                        <input wire:model="invoiceQuantities.{{ $line->id }}" aria-label="Fatura miktarı">
                    </div>
                @endforeach
                <x-v65.button wire:click="createInvoice" wire:loading.attr="disabled" wire:target="createInvoice">Alış Faturası Oluştur</x-v65.button>
            @endif

            @if(method_exists($this, 'reverse') && $document->status === 'posted')
                <label class="field"><span class="field-label">Ters Kayıt Tarihi</span><input type="date" wire:model="reversalDate"></label>
                <label class="field"><span class="field-label">Ters Kayıt Gerekçesi</span><input wire:model="reversalReason"></label>
                <x-v65.button variant="danger" wire:click="reverse" wire:loading.attr="disabled" wire:target="reverse" wire:confirm="Kaydedilmiş belgeye ters kayıt oluşturulacak. Devam edilsin mi?">Ters Kayıt</x-v65.button>
            @endif

            @if(isset($purchaseMatches) && $purchaseMatches->isNotEmpty())
                <h2>Üçlü Eşleştirme</h2>
                <div class="table-scroll v65-doc-matching-table"><table class="data-table">
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
                </table></div>
            @endif
        </section>
    @endif
</div>
