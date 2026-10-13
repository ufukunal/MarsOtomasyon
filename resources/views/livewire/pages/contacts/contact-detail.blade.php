<div class="v65-contact-detail stack">
    @php
        $fmt = \App\Support\Formatting\TrFormatter::class;
        $statuses = \App\Livewire\Pages\Contacts\ContactDetail::statusLabels();
        $money = fn ($value) => $fmt::money((string) ($value ?? '0'));
        $renderDate = fn ($date) => $date?->format('d.m.Y') ?? '—';
        $months = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık'];
        $typeLabels = [
            'quote' => 'Teklif', 'sales_order' => 'Satış Siparişi', 'purchase_order' => 'Satınalma Siparişi',
            'sales_invoice' => 'Satış Faturası', 'supplier_invoice' => 'Alış Faturası',
            'dispatch' => 'İrsaliye', 'goods_receipt' => 'Mal Kabul', 'proforma' => 'Proforma',
        ];
    @endphp

    @teleport('.page-actions.v65-page-actions')
        <div class="v65-contact-top-actions" aria-label="Cari işlemleri">
            @if(auth()->user()?->can('supplier_invoices.view') || auth()->user()?->can('purchase_orders.view'))
                <details class="v65-contact-action-menu">
                    <summary>Alım İşlemleri</summary>
                    <div class="v65-contact-action-panel">
                        @can('purchase_orders.view')<a href="{{ route('purchases.orders.index') }}">Alış Siparişleri</a>@endcan
                        @can('supplier_invoices.view')<a href="{{ route('purchases.invoices.index') }}">Alış Faturaları</a>@endcan
                    </div>
                </details>
            @endif
            @if(auth()->user()?->can('sales_invoices.view') || auth()->user()?->can('sales_orders.view') || auth()->user()?->can('quotes.view'))
                <details class="v65-contact-action-menu">
                    <summary>Satış İşlemleri</summary>
                    <div class="v65-contact-action-panel">
                        @can('quotes.view')<a href="{{ route('sales.quotes.index') }}">Teklifler</a>@endcan
                        @can('sales_orders.view')<a href="{{ route('sales.orders.index') }}">Satış Siparişleri</a>@endcan
                        @can('sales_invoices.view')<a href="{{ route('sales.invoices.index') }}">Satış Faturaları</a>@endcan
                    </div>
                </details>
            @endif
            @can('returns.view')
                <details class="v65-contact-action-menu">
                    <summary>İade İşlemleri</summary>
                    <div class="v65-contact-action-panel">
                        <a href="{{ route('returns.center') }}">İade Merkezi</a>
                    </div>
                </details>
            @endcan
            <details class="v65-contact-action-menu">
                <summary>Diğer</summary>
                <div class="v65-contact-action-panel">
                    @can('contacts.update')<a href="{{ route('contacts.edit', ['contact' => $contact->id]) }}">Cari Kartını Düzenle</a>@endcan
                    <a href="{{ route('contacts.index') }}">Cari Listesi</a>
                </div>
            </details>
            <button type="button" class="button-secondary" onclick="window.print()">Yazdır</button>
            <a class="button-secondary" href="{{ route('contacts.index') }}">Geri</a>
        </div>
    @endteleport

    <nav class="tabs v65-contact-tabs" role="tablist" aria-label="Cari detay sekmeleri">
        @foreach($tabs as $key => $label)
            <button type="button" role="tab" wire:key="contact-tab-{{ $key }}"
                @class(['tab', 'is-active' => $activeTab === $key])
                aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
                wire:click="selectTab('{{ $key }}')">{{ $label }}</button>
        @endforeach
    </nav>

    @if($activeTab === 'overview')
        <section class="v65-contact-identity" aria-label="Cari özeti">
            <div class="v65-contact-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($contact->title, 0, 2)) }}</div>
            <div class="v65-contact-identity-main">
                <strong>{{ $contact->title }}</strong>
                <span>{{ $contact->code }} · {{ $contact->category_names ?: 'Cari' }} · {{ collect([$contact->city, $contact->district])->filter()->implode(' / ') ?: 'Konum belirtilmedi' }}</span>
            </div>
            <div class="v65-contact-balance">
                <small>Cari Bakiye</small>
                @if($multiCurrency)
                    <strong>Para birimi bazında</strong>
                    @foreach($currencyBalances as $currencyRow)
                        <span>{{ $money($currencyRow->balance) }} {{ $currencyRow->currency }}</span>
                    @endforeach
                @else
                    <strong @class(['is-credit' => bccomp($balance, '0', 4) > 0, 'is-debit' => bccomp($balance, '0', 4) < 0])>
                        {{ $money(bccomp($balance, '0', 4) < 0 ? bcmul($balance, '-1', 4) : $balance) }} {{ $balanceCurrency }}
                        {{ bccomp($balance, '0', 4) > 0 ? 'Alacaklı' : (bccomp($balance, '0', 4) < 0 ? 'Borçlu' : 'Dengede') }}
                    </strong>
                @endif
            </div>
        </section>
        <div class="v65-contact-metrics">
            <div class="v65-contact-metric"><small>Cari Bakiye</small><strong>{{ $multiCurrency ? 'Çoklu para birimi' : $money($balance).' '.$balanceCurrency }}</strong></div>
            @if($yearSales !== null)
                <div class="v65-contact-metric"><small>{{ $year }} Satış (TRY)</small><strong>{{ $money($yearSales) }} ₺</strong></div>
            @endif
            @if($openOrders !== null)<div class="v65-contact-metric"><small>Açık Sipariş</small><strong>{{ $openOrders }}</strong></div>@endif
            @if($openQuotes !== null)<div class="v65-contact-metric"><small>Aktif Teklif</small><strong>{{ $openQuotes }}</strong></div>@endif
            <div class="v65-contact-metric"><small>Risk Limiti</small><strong>{{ $money($contact->risk_limit) }} ₺</strong></div>
            <div class="v65-contact-metric"><small>Son Hareket</small><strong>{{ $renderDate($lastMovement?->transaction_date) }}</strong></div>
        </div>

        <div class="v65-contact-overview-grid">
            <div class="v65-contact-overview-main">
                <section class="v65-contact-panel">
                    <div class="v65-contact-panel-header">
                        <h3>Son Cari Hareketler</h3>
                        <button type="button" class="button-secondary" wire:click="selectTab('movements')">Tüm Ekstre →</button>
                    </div>
                    <div class="table-scroll">
                        <table class="data-table">
                            <thead><tr><th>Tarih</th><th>Belge</th><th>İşlem</th><th>Borç</th><th>Alacak</th><th>PB</th></tr></thead>
                            <tbody>
                                @forelse($movements as $movement)
                                    <tr wire:key="contact-overview-move-{{ $movement->id }}">
                                        <td>{{ $renderDate($movement->transaction_date) }}</td>
                                        <td>{{ $movement->document?->number ?? '—' }}</td>
                                        <td>{{ $movement->description ?: $movement->transaction_type }}</td>
                                        <td class="align-end">{{ $movement->direction === 'debit' ? $money($movement->amount) : '—' }}</td>
                                        <td class="align-end">{{ $movement->direction === 'credit' ? $money($movement->amount) : '—' }}</td>
                                        <td>{{ $movement->currency }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="empty-state">Henüz kesinleşmiş cari hareketi yok.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="v65-contact-panel">
                    <div class="v65-contact-panel-header"><h3>Devam Eden İşler</h3></div>
                    <div class="table-scroll">
                        <table class="data-table">
                            <thead><tr><th>Tür</th><th>No</th><th>Tarih</th><th>Tutar</th><th>Durum</th></tr></thead>
                            <tbody>
                                @forelse($activity as $doc)
                                    <tr wire:key="contact-open-document-{{ $doc->id }}">
                                        <td>{{ $typeLabels[$doc->document_type->value] ?? $doc->document_type->value }}</td>
                                        <td>@if($this->documentUrl($doc))<a href="{{ $this->documentUrl($doc) }}">{{ $doc->number ?? 'Taslak' }}</a>@else{{ $doc->number ?? 'Taslak' }}@endif</td>
                                        <td>{{ $renderDate($doc->document_date) }}</td>
                                        <td class="align-end">{{ $money($doc->grand_total) }} {{ $doc->currency }}</td>
                                        <td><span class="v65-contact-state">{{ $statuses[$doc->status] ?? $doc->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="empty-state">Devam eden teklif, sipariş veya fatura bulunmuyor.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <aside class="v65-contact-overview-aside">
                <section class="v65-contact-panel">
                    <div class="v65-contact-panel-header"><h3>İletişim Özeti</h3></div>
                    <div class="v65-contact-panel-body">
                        <strong>{{ $contact->primary_contact_name ?: $contact->title }}</strong>
                        <p>{{ $contact->phone ?: 'Telefon belirtilmedi' }}</p>
                        <p>{{ $contact->email ?: 'E-posta belirtilmedi' }}</p>
                        <div class="v65-contact-divider"></div>
                        <p>{{ $contact->city }}{{ $contact->district ? ' / '.$contact->district : '' }}</p>
                        @if($contact->address)<p>{{ $contact->address }}</p>@endif
                    </div>
                </section>
                <section class="v65-contact-panel">
                    <div class="v65-contact-panel-header"><h3>Ticari Koşullar</h3></div>
                    <div class="v65-contact-panel-body">
                        <p>Vade: <strong>{{ $contact->term_days ?? '—' }} gün</strong></p>
                        <p>İskonto: <strong>%{{ number_format((float) $contact->discount_rate, 2, ',', '.') }}</strong></p>
                        <p>Fiyat Listesi: <strong>{{ $contact->priceList?->name ?? 'Varsayılan' }}</strong></p>
                    </div>
                </section>
            </aside>
        </div>

    @elseif(in_array($activeTab, ['invoices','orders','quotes'], true))
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header">
                <div class="v65-contact-panel-heading">
                    <h3>{{ $tabs[$activeTab] }}</h3>
                    <small>{{ $contact->code }} · {{ $contact->title }}</small>
                </div>
                <div class="v65-contact-panel-controls">
                    @if($activeTab === 'quotes')
                        @can('quotes.create')<a class="button-link" href="{{ route('sales.quotes.edit', ['cari' => $contact->id]) }}">+ Yeni Teklif</a>@endcan
                    @elseif($activeTab === 'orders')
                        @can('sales_orders.create')<a class="button-link" href="{{ route('sales.orders.edit', ['cari' => $contact->id]) }}">+ Yeni Sipariş</a>@endcan
                    @elseif($activeTab === 'invoices')
                        @can('sales_invoices.create')<a class="button-link" href="{{ route('sales.invoices.edit', ['cari' => $contact->id]) }}">+ Yeni Satış Faturası</a>@endcan
                    @endif
                    <button type="button" class="button-secondary"
                        wire:click="exportContactDocumentsCsv" wire:loading.attr="disabled"
                        wire:target="exportContactDocumentsCsv">CSV</button>
                    <button type="button" class="button-secondary"
                        wire:click="exportContactDocumentsXlsx" wire:loading.attr="disabled"
                        wire:target="exportContactDocumentsXlsx">Excel</button>
                </div>
            </div>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        @if($activeTab === 'quotes')
                            <tr><th>Teklif No</th><th>Tarih</th><th>Geçerlilik</th><th class="align-end">Tutar</th><th>Durum</th></tr>
                        @elseif($activeTab === 'orders')
                            <tr><th>Sipariş No</th><th>Tarih</th><th>Tür</th><th>Vade</th><th class="align-end">Tutar</th><th>Durum</th></tr>
                        @else
                            <tr><th>Fatura No</th><th>Tarih</th><th>Tür</th><th>Vade</th><th class="align-end">Matrah</th><th class="align-end">KDV</th><th class="align-end">Genel Toplam</th><th>Durum</th></tr>
                        @endif
                    </thead>
                    <tbody>
                        @forelse($documents as $doc)
                            <tr wire:key="contact-document-{{ $doc->id }}">
                                <td>@if($this->documentUrl($doc))<a href="{{ $this->documentUrl($doc) }}">{{ $doc->number ?? 'Taslak' }}</a>@else{{ $doc->number ?? 'Taslak' }}@endif</td>
                                <td>{{ $renderDate($doc->document_date) }}</td>
                                @if($activeTab === 'quotes')
                                    <td>{{ $renderDate($doc->valid_until) }}</td>
                                    <td class="align-end">{{ $money($doc->grand_total) }} {{ $doc->currency }}</td>
                                @elseif($activeTab === 'orders')
                                    <td>{{ $typeLabels[$doc->document_type->value] ?? $doc->document_type->value }}</td>
                                    <td>{{ $renderDate($doc->due_date) }}</td>
                                    <td class="align-end">{{ $money($doc->grand_total) }} {{ $doc->currency }}</td>
                                @else
                                    <td>{{ $typeLabels[$doc->document_type->value] ?? $doc->document_type->value }}</td>
                                    <td>{{ $renderDate($doc->due_date) }}</td>
                                    <td class="align-end">{{ $money($doc->tax_base) }} {{ $doc->currency }}</td>
                                    <td class="align-end">{{ $money($doc->vat_amount) }} {{ $doc->currency }}</td>
                                    <td class="align-end">{{ $money($doc->grand_total) }} {{ $doc->currency }}</td>
                                @endif
                                <td><span @class(['v65-contact-state','is-success'=>in_array($doc->status,['posted','confirmed'],true),'is-warning'=>in_array($doc->status,['draft','customer_review','internal_review'],true)])>{{ $statuses[$doc->status] ?? $doc->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $activeTab === 'quotes' ? 5 : ($activeTab === 'orders' ? 6 : 8) }}" class="empty-state">Bu cari için kayıt bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($documents)<x-v65.list-footer :paginator="$documents" :per-page="$perPage" />@endif
        </section>
    

    @elseif($activeTab === 'movements')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header">
                <div class="v65-contact-panel-heading">
                    <h3>{{ $statementMode === 'vouchers' ? 'Fiş Listesi' : ($statementMode === 'detailed' ? 'Detaylı Cari Ekstre' : 'Detaysız Cari Ekstre') }}</h3>
                    <small>{{ $contact->code }} · {{ $contact->title }}</small>
                </div>
                <div class="v65-contact-panel-controls">
                    <button type="button" @class(['button-secondary','is-current'=>$statementMode==='vouchers']) wire:click="switchStatementMode('vouchers')">Fiş Listesi</button>
                    <button type="button" @class(['button-secondary','is-current'=>$statementMode==='summary']) wire:click="switchStatementMode('summary')">Detaysız Ekstre</button>
                    <button type="button" @class(['button-secondary','is-current'=>$statementMode==='detailed']) wire:click="switchStatementMode('detailed')">Detaylı Ekstre</button>
                    <button type="button" class="button-secondary" wire:click="exportStatementCsv" wire:loading.attr="disabled" wire:target="exportStatementCsv">CSV</button>
                    <button type="button" class="button-secondary" wire:click="exportStatementXlsx" wire:loading.attr="disabled" wire:target="exportStatementXlsx">Excel</button>
                </div>
            </div>
            <div class="v65-contact-statement-filter">
                <label>Başlangıç <input type="date" wire:model.live.debounce.300ms="statementFrom" aria-label="Ekstre başlangıç tarihi"></label>
                <label>Bitiş <input type="date" wire:model.live.debounce.300ms="statementTo" aria-label="Ekstre bitiş tarihi"></label>
                <span>Fişler tarih sırasıyla listelenir. Önceki hareketlerin devir bakiyesi korunur.</span>
            </div>
            @if($movementTotals->isNotEmpty())
                <div class="v65-contact-statement-totals">
                    @foreach($movementTotals as $totalRow)
                        <div><small>Toplam Borç · {{ $totalRow->currency }}</small><strong>{{ $money($totalRow->total_debit) }}</strong></div>
                        <div><small>Toplam Alacak · {{ $totalRow->currency }}</small><strong>{{ $money($totalRow->total_credit) }}</strong></div>
                        <div><small>Hareket · {{ $totalRow->currency }}</small><strong>{{ $totalRow->total_movements }}</strong></div>
                    @endforeach
                </div>
            @endif
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr>
                        <th>Tarih</th><th>Belge No</th><th>İşlem</th><th>Açıklama</th>
                        <th class="align-end">Borç</th><th class="align-end">Alacak</th><th class="align-end">Bakiye</th><th>PB</th>
                    </tr></thead>
                    <tbody>
                        @forelse($movements as $move)
                            <tr wire:key="contact-movement-{{ $move->id }}" @class(['v65-statement-row','is-detail'=>$statementMode==='detailed'])>
                                <td>{{ $renderDate($move->transaction_date) }}</td>
                                <td>{{ $move->document?->number ?? '—' }}</td>
                                <td>{{ $move->transaction_type }}</td>
                                <td>{{ $move->description ?: '—' }}</td>
                                <td class="align-end">{{ $move->direction === 'debit' ? $money($move->amount) : '—' }}</td>
                                <td class="align-end">{{ $move->direction === 'credit' ? $money($move->amount) : '—' }}</td>
                                <td class="align-end"><strong>{{ $money($move->running_balance) }}</strong></td>
                                <td>{{ $move->currency }}</td>
                            </tr>
                            @if($statementMode === 'detailed' && $move->document && $this->documentUrl($move->document) && $move->document->lines->isNotEmpty())
                                <tr wire:key="contact-movement-lines-{{ $move->id }}" class="v65-statement-lines-row">
                                    <td></td><td colspan="7">
                                        <div class="v65-statement-lines-wrap">
                                            <table class="data-table">
                                                <thead><tr><th>Ürün Kodu</th><th>Ürün / Açıklama</th><th>Miktar</th><th>Birim</th><th>Fiyat</th><th>KDV</th><th>Satır Toplamı</th></tr></thead>
                                                <tbody>
                                                @foreach($move->document->lines as $line)
                                                    <tr wire:key="statement-line-{{ $move->id }}-{{ $line->id }}">
                                                        <td>{{ $line->product?->code ?? '—' }}</td>
                                                        <td>{{ $line->product?->name ?? $line->description ?? '—' }}</td>
                                                        <td class="align-end">{{ $fmt::quantity((string)$line->quantity) }}</td>
                                                        <td>{{ $line->unit?->name ?? '—' }}</td>
                                                        <td class="align-end">{{ $money($line->unit_price) }}</td>
                                                        <td class="align-end">%{{ number_format((float) $line->vat_rate,2,',','.') }}</td>
                                                        <td class="align-end">{{ $money($line->line_total) }}</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="8" class="empty-state">Cari hareketi bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-v65.list-footer :paginator="$movements" :per-page="$perPage" />
        </section>
    

    @elseif($activeTab === 'products')
        <div class="v65-contact-two-column">
            <div class="v65-contact-section-column">
                <section class="v65-contact-panel">
                    <div class="v65-contact-panel-header"><h3>En Çok Alınan Ürünler</h3><small>Kesinleşmiş satış faturaları · TRY</small></div>
                    <div class="table-scroll"><table class="data-table">
                        <thead><tr><th>Ürün</th><th>Miktar</th><th class="align-end">Ciro</th><th>Son Alım</th><th class="align-end">Ort. Fiyat</th></tr></thead>
                        <tbody>
                        @forelse($products as $row)
                            <tr>
                                <td>@can('products.view')<a href="{{ route('products.show', ['product'=>$row->id]) }}">{{ $row->code }} · {{ $row->name }}</a>@else{{ $row->code }} · {{ $row->name }}@endcan</td>
                                <td>{{ $fmt::quantity((string)$row->quantity) }} {{ $row->unit_name }}</td>
                                <td class="align-end">{{ $money($row->revenue) }} ₺</td>
                                <td>{{ $row->last_sale_at ? \Illuminate\Support\Carbon::parse($row->last_sale_at)->format('d.m.Y') : '—' }}</td>
                                <td class="align-end">{{ bccomp((string)$row->quantity,'0',3)>0 ? $money(bcdiv((string)$row->revenue,(string)$row->quantity,4)).' ₺' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">Kesinleşmiş TL satış faturası bulunmuyor.</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                </section>
                <section class="v65-contact-panel">
                    <div class="v65-contact-panel-header"><h3>Son Ürün Hareketleri</h3></div>
                    <div class="table-scroll"><table class="data-table">
                        <thead><tr><th>Tarih</th><th>Ürün</th><th>Belge</th><th>Miktar</th><th class="align-end">Fiyat</th><th class="align-end">Tutar</th></tr></thead>
                        <tbody>
                        @forelse($recentProductLines as $line)
                            <tr><td>{{ $line->document_date ? \Illuminate\Support\Carbon::parse($line->document_date)->format('d.m.Y') : '—' }}</td>
                                <td>{{ $line->code }}</td><td>{{ $line->number ?: '—' }}</td>
                                <td>{{ $fmt::quantity((string)$line->quantity) }} {{ $line->unit_name }}</td>
                                <td class="align-end">{{ $money($line->unit_price) }}</td>
                                <td class="align-end">{{ $money($line->line_total) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Ürün hareketi bulunmadı.</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                </section>
            </div>
            <div class="v65-contact-section-column">
                <section class="v65-contact-panel">
                    <div class="v65-contact-panel-header"><h3>Kategori Dağılımı</h3><small>TRY satış tutarı payı</small></div>
                    <div class="v65-contact-category-bars">
                        @forelse($productCategories as $category)
                            <div class="v65-contact-category-row">
                                <div><strong>{{ $category->category }}</strong><span>%{{ number_format($category->percent,1,',','.') }}</span></div>
                                <div class="v65-contact-category-track"><span style="width: {{ $category->percent }}%"></span></div>
                            </div>
                        @empty
                            <p>Kesinleşmiş satış kaydı bulunmadı.</p>
                        @endforelse
                    </div>
                </section>
                <section class="v65-contact-panel">
                    <div class="v65-contact-panel-header"><h3>Analiz Özeti</h3></div>
                    <div class="v65-contact-panel-body">
                        <p>Listelenen farklı ürün: <strong>{{ $productAnalysis['distinct'] ?? 0 }}</strong></p>
                        <p>En çok ciro üreten: <strong>{{ $productAnalysis['top_product'] ?? '—' }}</strong></p>
                        <p>TRY satış toplamı: <strong>{{ $money($productAnalysis['revenue_try'] ?? '0') }} ₺</strong></p>
                        <small>Farklı para birimindeki belgeler toplam dışındadır; adetler ürün bazında gruplanır.</small>
                    </div>
                </section>
            </div>
        </div>
    

    @elseif($activeTab === 'annual')
        <div class="v65-contact-metrics">
            <div class="v65-contact-metric"><small>{{ $year }} Satış</small><strong>{{ $money($annualSummary['sales_try'] ?? '0') }} ₺</strong></div>
            <div class="v65-contact-metric"><small>Satış Faturası</small><strong>{{ $annualSummary['sales_count'] ?? 0 }}</strong></div>
            <div class="v65-contact-metric"><small>Satış Olan Ay</small><strong>{{ $annualSummary['months'] ?? 0 }}</strong></div>
            <div class="v65-contact-metric"><small>Son Satış Ayı</small><strong>{{ isset($annualSummary['last_month']) ? ($months[$annualSummary['last_month']] ?? '—') : '—' }}</strong></div>
            <div class="v65-contact-metric"><small>Vade</small><strong>{{ $contact->term_days ?? '—' }} gün</strong></div>
            <div class="v65-contact-metric"><small>Risk Limiti</small><strong>{{ $money($contact->risk_limit) }} ₺</strong></div>
        </div>
        <div class="v65-contact-two-column">
            <section class="v65-contact-panel">
                <div class="v65-contact-panel-header"><h3>{{ $year }} Aylık Performans</h3><small>TRY kesinleşmiş satışlar</small></div>
                <div class="table-scroll"><table class="data-table">
                    <thead><tr><th>Ay</th><th class="align-end">Satış</th><th class="align-end">Fatura Sayısı</th></tr></thead>
                    <tbody>
                    @forelse($monthly as $row)
                        <tr><td>{{ $months[$row->month] ?? $row->month }}</td><td class="align-end">{{ $money($row->revenue) }} ₺</td><td class="align-end">{{ $row->invoices }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="empty-state">Bu yıl kesinleşmiş satış bulunmuyor.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
            <section class="v65-contact-panel">
                <div class="v65-contact-panel-header"><h3>Yıllık Değerlendirme</h3></div>
                <div class="table-scroll"><table class="data-table">
                    <thead><tr><th>Yıl</th><th>Para Birimi</th><th>Fatura Sayısı</th><th class="align-end">Satış Toplamı</th></tr></thead>
                    <tbody>
                    @forelse($annual as $row)
                        <tr><td>{{ $row->year }}</td><td>{{ $row->currency }}</td><td>{{ $row->document_count }}</td><td class="align-end">{{ $money($row->total) }} {{ $row->currency }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">Yıllık performans kaydı bulunmadı.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <p class="v65-contact-note">Trend, sınıf ve tahsilat disiplini gibi göstergeler hesaplanmadan varsayılan değer gösterilmez.</p>
            </section>
        </div>
    

    @elseif($activeTab === 'addresses')
        <div class="v65-contact-address-grid">
            @forelse($addresses as $address)
                <section class="v65-contact-address-card" wire:key="contact-address-card-{{ $address->id }}">
                    <strong>{{ $address->title ?: ($address->type === 'invoice' ? 'Fatura Adresi' : 'Sevk Adresi') }}</strong>
                    <p>{{ $address->address }}</p>
                    <p>{{ collect([$address->district,$address->city])->filter()->implode(' / ') ?: '—' }}</p>
                    <div class="v65-contact-address-separator"></div>
                    <span @class(['v65-contact-state','is-success'=>$address->is_default])>{{ $address->is_default ? 'Varsayılan ' : '' }}{{ $address->type === 'invoice' ? 'Fatura Adresi' : 'Sevk Adresi' }}</span>
                </section>
            @empty
                <section class="v65-contact-panel"><p class="v65-contact-note">Cari için kayıtlı adres bulunmuyor.</p></section>
            @endforelse
        </div>
        @can('contacts.update')
            <div class="v65-contact-hint">Adres değişikliği için <a href="{{ route('contacts.edit', ['contact'=>$contact->id]) }}">Cari Düzenle</a> ekranını kullanın.</div>
        @endcan
    

    @elseif($activeTab === 'people')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header">
                <div class="v65-contact-panel-heading"><h3>İletişim</h3><small>{{ $contact->code }} · {{ $contact->title }}</small></div>
                @can('contacts.update')<a class="button-secondary" href="{{ route('contacts.edit', ['contact'=>$contact->id]) }}">İletişimi Düzenle</a>@endcan
            </div>
        </section>
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header"><h3>Firma İletişim Kanalları</h3></div>
            <div class="v65-contact-definition-grid">
                <div><small>Ana Telefon</small><strong>{{ $contact->phone ?: '—' }}</strong></div>
                <div><small>Genel E-Posta</small><strong>{{ $contact->email ?: '—' }}</strong></div>
                <div><small>İl / İlçe</small><strong>{{ collect([$contact->city,$contact->district])->filter()->implode(' / ') ?: '—' }}</strong></div>
                <div><small>Varsayılan Yetkili</small><strong>{{ $contact->people->firstWhere('is_default',true)?->name ?: '—' }}</strong></div>
            </div>
        </section>
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header"><h3>Yetkililer</h3><small>{{ $contact->people->count() }} kişi</small></div>
            <div class="v65-contact-people-list">
                @forelse($contact->people as $person)
                    <div class="v65-contact-person" wire:key="contact-person-card-{{ $person->id }}">
                        <div><small>Ad Soyad</small><strong>{{ $person->name }} @if($person->is_default)<span class="v65-contact-state is-success">Birincil</span>@endif</strong></div>
                        <div><small>Görev</small><strong>{{ $person->title ?: '—' }}</strong></div>
                        <div><small>Cep Telefonu</small><strong>{{ $person->phone ?: '—' }}</strong></div>
                        <div><small>E-Posta</small><strong>{{ $person->email ?: '—' }}</strong></div>
                    </div>
                @empty
                    <p class="v65-contact-note">Kayıtlı yetkili bulunmuyor.</p>
                @endforelse
            </div>
        </section>
    

    @elseif($activeTab === 'invoice_info')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header">
                <h3>Fatura Bilgileri</h3>
                @can('contacts.update')<a href="{{ route('contacts.edit',['contact'=>$contact->id]) }}">Düzenle</a>@endcan
            </div>
            <div class="v65-contact-definition-grid v65-contact-invoice-info">
                <div><small>Fatura Unvanı</small><strong>{{ $contact->title }}</strong></div>
                <div><small>Vergi Dairesi</small><strong>{{ $contact->tax_office ?: '—' }}</strong></div>
                <div><small>Vergi No</small><strong>{{ $contact->tax_number ?: '—' }}</strong></div>
                <div><small>Fatura Adresi</small><strong>{{ $contact->addresses->firstWhere('type','invoice')?->address ?: ($contact->address ?: '—') }}</strong></div>
                <div><small>Vade</small><strong>{{ $contact->term_days ?? '—' }} gün</strong></div>
                <div><small>İskonto</small><strong>%{{ number_format((float)$contact->discount_rate,2,',','.') }}</strong></div>
                <div><small>Risk Limiti</small><strong>{{ $money($contact->risk_limit) }} ₺</strong></div>
                <div><small>Fiyat Sistemi</small><strong>{{ $contact->priceList?->name ?? 'Varsayılan liste' }}</strong></div>
            </div>
            <p class="v65-contact-note">E-Fatura etiketi ve senaryosu gibi kayıtlı olmayan alanlar gösterilmez.</p>
        </section>
    @elseif($activeTab === 'shipping')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header">
                <h3>Sevk Bilgileri</h3>
                @can('contacts.update')<a href="{{ route('contacts.edit',['contact'=>$contact->id]) }}">Sevk Adresini Düzenle</a>@endcan
            </div>
            <div class="v65-contact-definition-grid v65-contact-invoice-info">
                <div><small>Varsayılan Sevk Adresi</small><strong>{{ $contact->addresses->where('type','shipping')->firstWhere('is_default',true)?->address ?: ($contact->addresses->firstWhere('type','shipping')?->address ?: '—') }}</strong></div>
                <div><small>İl</small><strong>{{ $contact->addresses->firstWhere('type','shipping')?->city ?: ($contact->city ?: '—') }}</strong></div>
                <div><small>İlçe</small><strong>{{ $contact->addresses->firstWhere('type','shipping')?->district ?: ($contact->district ?: '—') }}</strong></div>
                <div><small>Telefon</small><strong>{{ $contact->phone ?: '—' }}</strong></div>
                <div><small>İletişim Yetkilisi</small><strong>{{ $contact->primary_contact_name ?: '—' }}</strong></div>
            </div>
            <p class="v65-contact-note">Teslimat günleri, saatleri, taşıyıcı ve ambar firma tercihleri cari veritabanında tanımlı olmadığından örnek değer gösterilmiyor.</p>
        </section>
    

    @elseif($activeTab === 'banks')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header">
                <h3>Banka Bilgileri</h3>
                @can('contacts.update')<a href="{{ route('contacts.edit', ['contact' => $contact->id]) }}">Banka Bilgilerini Düzenle</a>@endcan
            </div>
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>Banka</th><th>IBAN</th><th>Hesap Sahibi</th><th>Varsayılan</th></tr></thead><tbody>
                @forelse($contact->banks as $bank)
                    <tr><td>{{ $bank->bank_name ?: '—' }}</td><td>{{ $bank->iban }}</td><td>{{ $contact->title }}</td><td>{{ $bank->is_default ? 'Evet' : 'Hayır' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="empty-state">Banka kaydı bulunamadı.</td></tr>
                @endforelse
                </tbody></table></div>
        </section>
    

    @elseif($activeTab === 'notes')
        <div class="v65-contact-two-column">
            <section class="v65-contact-panel">
                <div class="v65-contact-panel-header"><h3>Cari Notu</h3></div>
                <div class="v65-contact-panel-body">Cari kartında kayıtlı cari notu alanı bulunmuyor.</div>
            </section>
            <section class="v65-contact-panel">
                <div class="v65-contact-panel-header"><h3>İç Not</h3></div>
                <div class="v65-contact-panel-body">İç not alanı mevcut veri modelinde bulunmuyor.</div>
            </section>
        </div>
    
    @elseif($activeTab === 'b2b')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header"><h3>B2B / Bayi</h3></div>
            <div class="v65-contact-definition-grid">
                <div><small>Cari</small><strong>{{ $contact->title }}</strong></div>
                <div><small>Cari İskontosu</small><strong>%{{ number_format((float) $contact->discount_rate, 2, ',', '.') }}</strong></div>
                <div><small>Fiyat Listesi</small><strong>{{ $contact->priceList?->name ?? 'Varsayılan' }}</strong></div>
            </div>
            <p class="v65-contact-note">B2B giriş sayısı, oturum ve bayi portalı istatistikleri mevcut veri modelinde bulunmuyor; örnek değer gösterilmiyor.</p>
        </section>
    @elseif($activeTab === 'files')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header"><h3>Dosyalar</h3></div>
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>Dosya</th><th>Tür</th><th>Boyut</th><th>Eklenme</th></tr></thead><tbody>
                @forelse($attachments as $file)
                    <tr><td>{{ $file->original_name }}</td><td>{{ $file->mime }}</td><td>{{ number_format(($file->size ?? 0) / 1024, 1, ',', '.') }} KB</td><td>{{ $file->created_at?->format('d.m.Y') }}</td></tr>
                @empty
                    <tr><td colspan="4" class="empty-state">Bu cariye bağlı dosya bulunamadı.</td></tr>
                @endforelse
                </tbody></table></div>
            <p class="v65-contact-note">Dosyalar yalnız yetkili yükleme/indirme akışından açılır; korumasız doğrudan depolama bağlantısı sunulmaz.</p>
        </section>
    @endif
</div>
