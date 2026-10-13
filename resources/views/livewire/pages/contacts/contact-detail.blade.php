<div class="v65-contact-detail stack">
    @php
        $fmt = \App\Support\Formatting\TrFormatter::class;
        $statuses = \App\Livewire\Pages\Contacts\ContactDetail::statusLabels();
        $money = fn ($value) => $fmt::money((string) ($value ?? '0'));
        $renderDate = fn ($date) => $date?->format('d.m.Y') ?? '—';
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
                <strong @class(['is-debit' => bccomp($balance, '0', 4) > 0, 'is-credit' => bccomp($balance, '0', 4) < 0])>
                    {{ $money(bccomp($balance, '0', 4) < 0 ? bcmul($balance, '-1', 4) : $balance) }} ₺
                    {{ bccomp($balance, '0', 4) > 0 ? 'Borç' : (bccomp($balance, '0', 4) < 0 ? 'Alacak' : 'Dengede') }}
                </strong>
            </div>
        </section>
        <div class="v65-contact-metrics">
            <div class="v65-contact-metric"><small>Cari Bakiye</small><strong>{{ $money($balance) }} ₺</strong></div>
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
                <h3>{{ $tabs[$activeTab] }}</h3>
            </div>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Belge No</th><th>Tarih</th><th>Tür</th><th>Vade</th><th>Genel Toplam</th><th>Durum</th></tr></thead>
                    <tbody>
                        @forelse($documents as $doc)
                            <tr wire:key="contact-document-{{ $doc->id }}">
                                <td>@if($this->documentUrl($doc))<a href="{{ $this->documentUrl($doc) }}">{{ $doc->number ?? 'Taslak' }}</a>@else{{ $doc->number ?? 'Taslak' }}@endif</td>
                                <td>{{ $renderDate($doc->document_date) }}</td>
                                <td>{{ $typeLabels[$doc->document_type->value] ?? $doc->document_type->value }}</td>
                                <td>{{ $renderDate($doc->due_date) }}</td>
                                <td class="align-end">{{ $money($doc->grand_total) }} {{ $doc->currency }}</td>
                                <td><span class="v65-contact-state">{{ $statuses[$doc->status] ?? $doc->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Bu cari için kayıt bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($documents)<x-v65.list-footer :paginator="$documents" :per-page="$perPage" />@endif
        </section>
    @elseif($activeTab === 'movements')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header"><h3>Cari Hareketler</h3></div>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Tarih</th><th>Belge</th><th>İşlem</th><th>Borç</th><th>Alacak</th><th>Para Birimi</th></tr></thead>
                    <tbody>
                        @forelse($movements as $move)
                            <tr wire:key="contact-movement-{{ $move->id }}">
                                <td>{{ $renderDate($move->transaction_date) }}</td>
                                <td>{{ $move->document?->number ?? '—' }}</td>
                                <td>{{ $move->description ?: $move->transaction_type }}</td>
                                <td class="align-end">{{ $move->direction === 'debit' ? $money($move->amount) : '—' }}</td>
                                <td class="align-end">{{ $move->direction === 'credit' ? $money($move->amount) : '—' }}</td>
                                <td>{{ $move->currency }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Cari hareketi bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-v65.list-footer :paginator="$movements" :per-page="$perPage" />
        </section>
    @elseif($activeTab === 'products')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header"><h3>Ürün Analizi</h3><small>Kesinleşmiş satış faturalarından hesaplanır.</small></div>
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>Ürün Kodu</th><th>Ürün Adı</th><th>Satılan Miktar</th><th>Belge Sayısı</th></tr></thead>
                <tbody>
                @forelse($products as $row)
                    <tr><td>@can('products.view')<a href="{{ route('products.show', ['product' => $row->id]) }}">{{ $row->code }}</a>@else{{ $row->code }}@endcan</td><td>{{ $row->name }}</td><td class="align-end">{{ $fmt::quantity((string) $row->quantity) }}</td><td class="align-end">{{ $row->document_count }}</td></tr>
                @empty
                    <tr><td colspan="4" class="empty-state">Kesinleşmiş satış kaydı bulunamadı.</td></tr>
                @endforelse
                </tbody></table></div>
        </section>
    @elseif($activeTab === 'annual')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header"><h3>Yıllık Performans</h3><small>Para birimleri ayrı toplamlanır.</small></div>
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>Yıl</th><th>Para Birimi</th><th>Satış Faturası</th><th>Kesinleşmiş Satış Toplamı</th></tr></thead>
                <tbody>
                @forelse($annual as $row)
                    <tr><td>{{ $row->year }}</td><td>{{ $row->currency }}</td><td>{{ $row->document_count }}</td><td class="align-end">{{ $money($row->total) }} {{ $row->currency }}</td></tr>
                @empty
                    <tr><td colspan="4" class="empty-state">Yıllık satış performansı için kesinleşmiş kayıt bulunamadı.</td></tr>
                @endforelse
                </tbody></table></div>
        </section>
    @elseif($activeTab === 'addresses' || $activeTab === 'shipping')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header">
                <h3>{{ $tabs[$activeTab] }}</h3>
                @can('contacts.update')<a href="{{ route('contacts.edit', ['contact' => $contact->id]) }}">Adresleri Düzenle</a>@endcan
            </div>
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>Tür</th><th>Başlık</th><th>Adres</th><th>İl / İlçe</th><th>Varsayılan</th></tr></thead><tbody>
                @forelse($addresses as $address)
                    <tr><td>{{ $address->type === 'invoice' ? 'Fatura' : 'Sevk' }}</td><td>{{ $address->title ?: '—' }}</td><td>{{ $address->address }}</td><td>{{ collect([$address->city,$address->district])->filter()->implode(' / ') }}</td><td>{{ $address->is_default ? 'Evet' : 'Hayır' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="empty-state">Adres kaydı bulunamadı.</td></tr>
                @endforelse
                </tbody></table></div>
        </section>
    @elseif($activeTab === 'people')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header">
                <h3>İletişim</h3>
                @can('contacts.update')<a href="{{ route('contacts.edit', ['contact' => $contact->id]) }}">Yetkilileri Düzenle</a>@endcan
            </div>
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>Ad Soyad</th><th>Unvan</th><th>Telefon</th><th>E-posta</th><th>Varsayılan</th></tr></thead><tbody>
                @forelse($contact->people as $person)
                    <tr><td>{{ $person->name }}</td><td>{{ $person->title ?: '—' }}</td><td>{{ $person->phone ?: '—' }}</td><td>{{ $person->email ?: '—' }}</td><td>{{ $person->is_default ? 'Evet' : 'Hayır' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="empty-state">İletişim yetkilisi bulunamadı.</td></tr>
                @endforelse
                </tbody></table></div>
        </section>
    @elseif($activeTab === 'invoice_info')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header">
                <h3>Fatura Bilgileri</h3>
                @can('contacts.update')<a href="{{ route('contacts.edit', ['contact' => $contact->id]) }}">Düzenle</a>@endcan
            </div>
            <div class="v65-contact-definition-grid">
                <div><small>Cari Unvan</small><strong>{{ $contact->title }}</strong></div>
                <div><small>Vergi Dairesi</small><strong>{{ $contact->tax_office ?: '—' }}</strong></div>
                <div><small>Vergi Numarası</small><strong>{{ $contact->tax_number ?: '—' }}</strong></div>
                <div><small>Telefon</small><strong>{{ $contact->phone ?: '—' }}</strong></div>
                <div><small>E-posta</small><strong>{{ $contact->email ?: '—' }}</strong></div>
                <div><small>Fatura Adresi</small><strong>{{ $contact->addresses->firstWhere('type', 'invoice')?->address ?: ($contact->address ?: '—') }}</strong></div>
            </div>
        </section>
    @elseif($activeTab === 'banks')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header">
                <h3>Banka Bilgileri</h3>
                @can('contacts.update')<a href="{{ route('contacts.edit', ['contact' => $contact->id]) }}">Banka Bilgilerini Düzenle</a>@endcan
            </div>
            <div class="table-scroll"><table class="data-table">
                <thead><tr><th>Banka</th><th>IBAN</th><th>Varsayılan</th></tr></thead><tbody>
                @forelse($contact->banks as $bank)
                    <tr><td>{{ $bank->bank_name ?: '—' }}</td><td>{{ $bank->iban }}</td><td>{{ $bank->is_default ? 'Evet' : 'Hayır' }}</td></tr>
                @empty
                    <tr><td colspan="3" class="empty-state">Banka kaydı bulunamadı.</td></tr>
                @endforelse
                </tbody></table></div>
        </section>
    @elseif($activeTab === 'notes')
        <section class="v65-contact-panel">
            <div class="v65-contact-panel-header"><h3>Özel Açıklamalar</h3></div>
            <div class="v65-contact-panel-body">Bu cari kaydında özel açıklama alanı bulunmuyor. Mevcut cari kartı bilgileri değiştirilmiyor.</div>
        </section>
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
