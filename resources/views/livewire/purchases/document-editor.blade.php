<div class="document-workspace stack">
    @php
        $isGoodsReceipt = $document?->document_type === \App\Enums\DocumentType::GoodsReceipt;
    @endphp

    @php
        $documentIndexRoute = match (true) {
            $this instanceof \App\Livewire\Purchases\PurchaseOrderEditor => 'purchases.orders.index',
            $this instanceof \App\Livewire\Purchases\GoodsReceiptEditor => 'purchases.receipts.index',
            default => 'purchases.invoices.index',
        };
    @endphp
    <x-v65.document-heading :document="$document" :title="$title" section="Satınalma Yönetimi" :index-route="$documentIndexRoute" />
    @if($errors->any())
        <x-v65.notice tone="danger" title="Belge kontrolü" data-v65-document-errors tabindex="-1">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </x-v65.notice>
    @endif
    @if($documentDirty)
        <x-v65.notice tone="warning">Kaydedilmemiş değişiklikler var. Belge işlemine devam etmeden önce Kaydet'i kullanın.</x-v65.notice>
    @endif
    @if($document && $document->status === 'draft')
        <x-v65.document-sources class="v65-doc-draft-sources" :document="$document" />
    @endif

    @php
        $draftPreview = $this->draftTotalsPreview();
    @endphp

    @if(!$document || $document->status === 'draft')
        <form wire:submit="save" class="document-editor-form v65-doc-editor">
            <div class="v65-doc-contact-picker">
                <label class="field">Tedarikçi Ara
                    <input type="search" wire:model.live.debounce.300ms="contactSearch" placeholder="Kod veya unvan ile ara" autocomplete="off">
                    <small class="v65-doc-search-hint">Kod veya unvan yazarak arayın. En fazla 35 eşleşme listelenir.</small>
                    <small class="v65-doc-search-loading" wire:loading wire:target="contactSearch">Aranıyor…</small>
                </label>
                <x-v65.select label="Tedarikçi" wire:model.live="contactId" placeholder="Tedarikçi seçin" class="v65-doc-contact">
                    @forelse($this->contactOptions() as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                    @empty
                        <option value="" disabled>Aramaya uygun cari bulunamadı</option>
                    @endforelse
                </x-v65.select>
                <x-v65.input-error field="contactId" />
            </div>
            <label class="field">Belge Tarihi <input type="date" wire:model="documentDate"><x-v65.input-error field="documentDate" /></label>
            @if(!$isGoodsReceipt)
                <label class="field">Vade <input type="date" wire:model="dueDate"><x-v65.input-error field="dueDate" /></label>
                <label class="field">Para Birimi <input wire:model="currency" maxlength="3"><x-v65.input-error field="currency" /></label>
                <label class="field">Kur <input wire:model.blur="exchangeRate" inputmode="decimal" data-tr-decimal><x-v65.input-error field="exchangeRate" /></label>
                <label class="field">Belge İskonto % <input wire:model.blur="discountRate" inputmode="decimal" data-tr-decimal><x-v65.input-error field="discountRate" /></label>
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
                    <small>Miktar, fiyat ve vergi değişince taslak toplamları güncellenir.</small>
                </div>
                <div class="table-scroll document-lines v65-doc-table-scroll"><table class="data-table v65-doc-table">
                <thead>
                <tr>
                    <th scope="col">#</th><th scope="col">Tür</th><th scope="col">Ürün / Açıklama</th><th scope="col">Birim</th><th scope="col" class="align-end">Miktar</th><th scope="col">Lokasyon</th>
                    @unless($isGoodsReceipt)
                        <th scope="col" class="align-end">Fiyat</th><th scope="col" class="align-end">İsk. %</th><th scope="col" class="align-end">KDV %</th>
                    @endunless
                    <th scope="col" class="align-end">İskonto Tutarı</th><th scope="col" class="align-end">Satır KDV*</th><th scope="col" class="align-end">Satır Net / Matrah</th>
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
                                <input type="search" class="v65-doc-product-search"
                                    wire:model.live.debounce.300ms="productSearches.{{ $line['row_key'] ?? $index }}"
                                    placeholder="Ürün kodu veya adıyla ara"
                                    aria-label="Satır {{ $index + 1 }} ürün ara" autocomplete="off">
                                    <small class="v65-doc-search-loading" wire:loading wire:target="productSearches.{{ $line['row_key'] ?? $index }}">Aranıyor…</small>
                                <select wire:model.live="lines.{{ $index }}.product_id" aria-label="Satır {{ $index + 1 }} ürün">
                                    <option value="">Ürün seçin</option>
                                    @forelse($this->productOptionsForLine((string) ($line['row_key'] ?? $index), (int) ($line['product_id'] ?? 0)) as $product)
                                        <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                                    @empty
                                        <option value="" disabled>Ürün bulunamadı</option>
                                    @endforelse
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
                        <td class="align-end"><input wire:model.blur="lines.{{ $index }}.quantity" aria-invalid="{{ $errors->has('lines.'.$index.'.quantity') ? 'true' : 'false' }}" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} miktar"><x-v65.input-error :field="'lines.'.$index.'.quantity'" /></td>
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
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.unit_price" aria-invalid="{{ $errors->has('lines.'.$index.'.unit_price') ? 'true' : 'false' }}" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} birim fiyat"><x-v65.input-error :field="'lines.'.$index.'.unit_price'" /></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.line_discount_rate" aria-invalid="{{ $errors->has('lines.'.$index.'.line_discount_rate') ? 'true' : 'false' }}" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} iskonto"><x-v65.input-error :field="'lines.'.$index.'.line_discount_rate'" /></td>
                            <td class="align-end"><input wire:model.blur="lines.{{ $index }}.vat_rate" aria-invalid="{{ $errors->has('lines.'.$index.'.vat_rate') ? 'true' : 'false' }}" inputmode="decimal" data-tr-decimal aria-label="Satır {{ $index + 1 }} KDV"><x-v65.input-error :field="'lines.'.$index.'.vat_rate'" /></td>
                        @endif
                        <x-v65.document-line-amounts :calculation="$draftPreview?->lines[$index] ?? null" :line="$line" :show-stored="!$documentDirty" />
                        <td class="align-end v65-doc-line-net">@if($draftPreview && isset($draftPreview->lines[$index])){{ \App\Support\Formatting\TrFormatter::money((string) $draftPreview->lines[$index]->lineTotal) }}@elseif(!$documentDirty && isset($line['line_total'])){{ \App\Support\Formatting\TrFormatter::money((string) $line['line_total']) }}@else—@endif</td>
                        @unless($isGoodsReceipt)
                            <td><x-v65.button variant="danger" size="xs" wire:click="removeLine({{ $index }})" aria-label="Satır {{ $index + 1 }} sil">Sil</x-v65.button></td>
                        @endunless
                    </tr>
                @endforeach
                </tbody>
            </table></div>
                <small class="v65-doc-line-tax-note">* Satır KDV tutarı, belge genel iskontosu uygulanmadan önceki bilgi amaçlı hesaplamadır. Geçerli toplam KDV, Belge Toplamları panelindeki sunucu hesaplamasıdır.</small>
            </section>

            <x-v65.form-actions align="between" class="v65-doc-form-actions">
                @unless($isGoodsReceipt)<x-v65.button variant="secondary" wire:click="addLine">+ Satır Ekle</x-v65.button>@endunless
                <x-v65.button type="submit" wire:loading.attr="disabled" wire:target="save">Kaydet</x-v65.button>
            </x-v65.form-actions>
        </form>
    @elseif($document)
        <x-v65.document-readonly :document="$document" />
    @endif

    @if($document && $document->status === 'draft')
        <div class="v65-doc-print-only">
            <x-v65.document-readonly :document="$document" />
            <x-v65.document-summary :document="$document" />
        </div>
    @endif

    <x-v65.document-summary class="v65-doc-screen-summary" :document="$document" :preview="$draftPreview" />

    @if($document)
        <section class="document-actions panel v65-document-actions v65-doc-workflow" role="group" aria-label="Belge işlemleri">
            <h3 class="v65-doc-workflow-title">Belge İşlemleri</h3>
            @if(method_exists($this, 'submitApproval') && $document->status === 'draft')
                <x-v65.document-confirm id="v65-purchase-submit" title="Onaya Gönder" label="Onaya Gönder" action="submitApproval" variant="secondary" :document="$document" message="Son kaydedilen alış siparişi onay sürecine gönderilecektir." />
            @endif

            @if(method_exists($this, 'approve') && $document->status === 'pending_approval')
                @can('purchase_orders.approve')
                    <x-v65.document-confirm id="v65-purchase-approve" title="Alış Siparişi Onayı" label="Onayla" action="approve" :document="$document" message="Alış siparişi son kaydedilen haliyle onaylanacaktır." />
                @endcan
            @endif

            @if(method_exists($this, 'sendToSupplier') && $document->status === 'approved')
                <x-v65.button variant="secondary" wire:click="sendToSupplier" wire:loading.attr="disabled" wire:target="sendToSupplier">Tedarikçiye Gönder</x-v65.button>
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
                <x-v65.document-confirm id="v65-purchase-post" title="Belgeyi Kesinleştir" label="Kesinleştir" action="post" :document="$document" message="Son kaydedilen alış belgesi sunucuda doğrulanıp kesinleştirilecektir. Fiyat sapması kabulünüz gerektiğinde işlem sırasında kontrol edilir." />
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
                <x-v65.document-confirm id="v65-purchase-reverse" title="Alış Ters Kayıt Onayı" label="Ters Kayıt" action="reverse" variant="danger" :document="$document" message="Kesinleşmiş alış belgesine ters kayıt oluşturulur. Tarih ve gerekçeyi kontrol edin." />
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
