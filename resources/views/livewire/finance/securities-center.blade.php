<div class="space-y-8 v65-list-page v65-ref-finance v65-ref-securities {{ $screenMode === 'endorse' ? 'v65-ref-security-is-endorse' : '' }}">
<header class="v65-ref-heading"><div><h1>{{ $screenTitle }}</h1><p>@if($screenMode === 'list') Alınan, verilen ve ciro edilen {{ $screenKind === 'check' ? 'çek' : 'senet' }} kayıtları
@elseif($screenMode === 'endorse') Alınan evrakı başka cariye devretme · Bağımsız işlem
@else Cari bakiye ve portföy etkisiyle {{ $screenKind === 'check' ? 'çek' : 'senet' }} kaydı @endif</p></div>
<span class="v65-ref-tag">{{ $screenMode === 'list' ? 'Portföy / Vade' : 'Bakiye esaslı · TRY' }}</span></header>
    @if($replacementSecurityId)
        <x-v65.notice tone="success">Mali düzeltme tamamlandı. Eski evrak terslendi; yeni evrak #{{ $replacementSecurityId }} oluşturuldu.</x-v65.notice>
    @endif
    @if($screenMode !== 'center')
        <section class="v65-ref-security-nav"><nav class="v65-security-nav" aria-label="Çek ve senet işlemleri">
                @foreach([
                    'finance.checks.index' => 'Çek Listesi', 'finance.checks.incoming' => 'Alınan Çek',
                    'finance.checks.outgoing' => 'Verilen Çek', 'finance.checks.endorsement' => 'Çek Ciro',
                    'finance.notes.index' => 'Senet Listesi', 'finance.notes.incoming' => 'Alınan Senet',
                    'finance.notes.outgoing' => 'Verilen Senet', 'finance.notes.endorsement' => 'Senet Ciro'
                ] as $route => $label)
                    <a href="{{ route($route) }}" class="v65-security-nav-link {{ request()->routeIs($route) ? 'is-current' : '' }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </section>
    @endif

    @if(in_array($screenMode, ['center', 'create'], true))
    <section class="panel stack v65-ref-card v65-ref-security-form">
        <header class="v65-ref-card-head"><strong>{{ $screenMode === 'center' ? 'Yeni Çek / Senet' : $screenTitle . ' Bilgileri' }}</strong><small>Bağımsız evrak kaydı · TRY</small></header>
        <h2 class="v65-ref-screenreader">{{ $screenMode === 'center' ? 'Yeni Çek / Senet' : $screenTitle }}</h2>
        @if($screenMode === 'center')
        <label class="field">Yön
            <select wire:model="direction">
                <option value="incoming">Alınan</option>
                <option value="outgoing">Verilen</option>
            </select>
        </label>
        <label class="field">Tür
            <select wire:model="kind">
                <option value="check">Çek</option>
                <option value="note">Senet</option>
            </select>
        </label>
        @else
            <p>{{ $screenDirection === 'incoming' ? 'Alınan' : 'Verilen' }} {{ $screenKind === 'check' ? 'Çek' : 'Senet' }} kaydı</p>
        @endif
        <label class="field">{{ ($screenKind ?? $kind) === 'check' ? 'Çek Numarası *' : 'Senet Numarası *' }} <input wire:model.blur="instrumentNo"></label>
        <label class="field">{{ ($screenDirection ?? $direction) === 'incoming' ? 'Çeki/Senedi Veren Cari *' : 'Çeki/Senedi Alacak Cari *' }}
            <input type="search" wire:model.live.debounce.300ms="securityContactSearch" placeholder="Cari kodu veya unvanı ara" autocomplete="off" aria-label="Evrak cari ara">
            <select wire:model="contactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">Düzenleme Tarihi * <input type="date" wire:model="transactionDate"></label>
        <label class="field">Vade Tarihi * <input type="date" wire:model="dueDate"></label>
        <label class="field">{{ ($screenKind ?? $kind) === 'check' ? 'Çek Tutarı *' : 'Senet Tutarı *' }} <input wire:model.blur="amount" inputmode="decimal" data-tr-decimal></label>
        <label class="field">Para Birimi <input readonly value="TRY · Türk Lirası"></label>
        @if(($screenKind ?? $kind) === 'check')
            @if(($screenDirection ?? $direction) === 'incoming')
                <label class="field">Muhatap Banka <input wire:model.blur="bankName"></label>
            @else
                <label class="field">Ödeme Yapılacak Banka Hesabı
                    <select wire:model="bankAccountId">
                        <option value="">Seçim yok</option>
                        @foreach($bankAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} · {{ $account->account_name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        @else
            <div class="field v65-ref-security-note-party"><strong>Borçlu / Lehtar</strong><small>Seçilen cari hesabın unvanı evrak tarafı olarak kaydedilir.</small></div>
        @endif
        <label class="field v65-ref-security-note">Açıklama <textarea wire:model.blur="note" rows="3"></textarea></label>
        <section class="v65-finance-impact" aria-label="Evrak ilk kayıt önizlemesi">
            <h3>İlk Kayıt Mali Etkisi <small>Banka hareketi ancak tahsil/ödeme bordrosunda oluşur.</small></h3>
            <div class="v65-impact-grid">
                <div class="v65-impact-card"><span>İlgili Cari Önce (Borç − Alacak)</span>
                    <strong class="{{ bccomp($this->selectedContactBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->selectedContactBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ $this->canViewSecurityContactBalance() ? \App\Support\Formatting\TrFormatter::money($this->selectedContactBalance()) . ' TRY' : 'Görüntüleme yetkisi yok' }}</strong></div>
                <div class="v65-impact-card"><span>İlgili Cari Sonra (Tahmini)</span>
                    <strong class="{{ bccomp($this->projectedContactBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->projectedContactBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ $this->canViewSecurityContactBalance() ? \App\Support\Formatting\TrFormatter::money($this->projectedContactBalance()) . ' TRY' : 'Görüntüleme yetkisi yok' }}</strong></div>
                <div class="v65-impact-card"><span>Başlangıç Banka Etkisi</span><strong>0,00 TRY</strong></div>
                <div class="v65-impact-card"><span>Yeni Evrak Yönü</span>
                    <strong>{{ ($screenDirection ?? $direction) === 'outgoing' ? 'Verilen: cari borcu artar' : 'Alınan: cari borcu azalır' }}</strong></div>
            </div>
            @if(($screenKind ?? $kind) === 'note')
                <small>Borçlu / lehtar bilgisi seçili cariye bağlıdır. Banka adı alanı ayrı bir bilgidir.</small>
            @endif
        </section>
        <x-v65.button wire:click="create" wire:loading.attr="disabled" wire:target="create">Kaydet</x-v65.button>
    </section>
    @endif

    @unless($screenMode === 'create')
    <section class="v65-ref-security-records">
        @if($screenMode === 'list')
            <section class="v65-security-toolbar" aria-label="Çek senet liste özeti ve filtreler">
                <div class="v65-security-summary">
                    <div class="v65-impact-card"><span>Toplam Evrak</span><strong>{{ $securitySummary['count'] }}</strong></div>
                    <div class="v65-impact-card"><span>Portföyde</span><strong>{{ $securitySummary['portfolio'] }}</strong></div>
                    <div class="v65-impact-card"><span>Bankada / Cirolu</span><strong>{{ $securitySummary['banked'] + $securitySummary['endorsed'] }}</strong></div>
                    <div class="v65-impact-card"><span>Ödenen / Tahsil Edilen</span><strong>{{ $securitySummary['settled'] }}</strong></div>
                </div>
                <div class="v65-security-filters">
                    <label class="field">Evrak No / Cari Ara<input type="search" wire:model.live.debounce.300ms="listSearch" placeholder="Evrak no, cari kodu veya unvan"></label>
                    <label class="field">İşlem Yönü
                        <select wire:model.live="listDirection">
                            <option value="">Tüm İşlemler</option><option value="incoming">Alınan</option><option value="outgoing">Verilen</option>
                        </select>
                    </label>
                    <label class="field">Durum
                        <select wire:model.live="listStatus">
                            <option value="">Tüm Durumlar</option>
                            @foreach(['portfolio' => 'Portföyde', 'issued' => 'Verildi', 'banked' => 'Bankada', 'endorsed' => 'Ciro Edildi', 'collected' => 'Tahsil Edildi', 'paid' => 'Ödendi', 'returned' => 'İade', 'protested' => 'Protesto', 'cancelled' => 'İptal'] as $status => $label)
                                <option value="{{ $status }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="v65-security-new-actions">
                        <x-v65.button variant="secondary" size="sm" :href="route($screenKind === 'check' ? 'finance.checks.incoming' : 'finance.notes.incoming')">+ Alınan {{ $screenKind === 'check' ? 'Çek' : 'Senet' }}</x-v65.button>
                        <x-v65.button variant="secondary" size="sm" :href="route($screenKind === 'check' ? 'finance.checks.outgoing' : 'finance.notes.outgoing')">+ Verilen {{ $screenKind === 'check' ? 'Çek' : 'Senet' }}</x-v65.button>
                        <x-v65.button variant="secondary" size="sm" :href="route($screenKind === 'check' ? 'finance.checks.endorsement' : 'finance.notes.endorsement')">+ Ciro</x-v65.button>
                    </div>
                </div>
            </section>
        @endif
        <x-v65.list-heading :title="$screenMode === 'center' ? 'Portföy' : $screenTitle . ' Kayıtları'" />
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead>
            <tr>
                @if($screenMode === 'list')
                    <th>No</th><th>{{ $screenKind === 'check' ? 'Çek Numarası' : 'Senet Numarası' }}</th><th>İşlem</th>
                @else
                    <th>Seç</th><th>No</th><th>Tür</th><th>Yön</th>
                @endif
                <th>İlgili Cari</th><th>Vade</th><th class="align-end">Tutar</th>
                <th class="align-end">Cari Etkisi (Kayıtta)</th><th>Durum</th>
                <th>{{ $screenMode === 'list' ? 'Sonraki İşlem' : 'İşlem' }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($securities as $security)
                <tr>
                    @if($screenMode === 'list')
                        <td>{{ ($securities->firstItem() ?? 1) + $loop->index }}</td>
                    @else
                        <td>
                            @if(in_array($screenMode, ['center', 'endorse'], true) && ($screenMode !== 'endorse' || ($security->direction === 'incoming' && $security->status === 'portfolio')))
                                <input type="checkbox" wire:model="selectedSecurityIds" value="{{ $security->id }}">
                            @else
                                —
                            @endif
                        </td>
                    @endif
                    <td><button type="button" class="link-button v65-record-link" wire:click="openSecurityRecord({{ $security->id }})">{{ $security->instrument_no }}</button></td>
                    @if($screenMode !== 'list')
                        <td>{{ \App\Support\Formatting\BusinessLabelFormatter::value('security_kind', $security->kind) }}</td>
                    @endif
                    <td>{{ \App\Support\Formatting\BusinessLabelFormatter::direction($security->direction) }}</td>
                    <td>{{ $security->contact?->title }}</td>
                    <td>{{ $security->due_date?->format('d.m.Y') }}</td>
                    <td class="align-end">{{ \App\Support\Formatting\TrFormatter::money((string) $security->amount) }} TRY</td>
                    <td class="align-end {{ $security->direction === 'outgoing' ? 'is-debtor' : 'is-creditor' }}">{{ $security->direction === 'outgoing' ? '+' : '−' }}{{ \App\Support\Formatting\TrFormatter::money((string) $security->amount) }} TRY</td>
                    <td>
                        @php
                            $securityTone = match ($security->status) {
                                'portfolio', 'issued' => 'warning',
                                'banked', 'endorsed' => 'info',
                                'collected', 'paid' => 'success',
                                'protested', 'returned', 'cancelled' => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <x-v65.status-badge :tone="$securityTone" :dot="true">{{ \App\Support\Formatting\BusinessLabelFormatter::status($security->status) }}</x-v65.status-badge>
                    </td>
                    <td>
                        <x-v65.button variant="secondary" size="xs" wire:click="openSecurityRecord({{ $security->id }})">Aç</x-v65.button>
                        @if($screenMode === 'list' && auth()->user()?->can('security_payrolls.create'))
                            <span class="v65-security-row-actions">
                                @if($security->direction === 'incoming' && $security->status === 'portfolio')
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'endorsement')">Ciro</x-v65.button>
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'bank_deposit')">Bankaya Ver</x-v65.button>
                                @endif
                                @if($security->direction === 'incoming' && in_array($security->status, ['portfolio', 'banked'], true))
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'collection')">Tahsil</x-v65.button>
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'return')">İade</x-v65.button>
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'protest')">Protesto</x-v65.button>
                                @endif
                                @if($security->direction === 'outgoing' && $security->status === 'issued')
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'payment')">Öde</x-v65.button>
                                    <x-v65.button size="xs" variant="danger" wire:click="prepareListPayroll({{ $security->id }}, 'cancel')">İptal</x-v65.button>
                                @endif
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="v65-security-pagination">{{ $securities->links() }}</div>
        @if(in_array($screenMode, ['center', 'endorse'], true))
            <x-v65.bulk-actions :count="count($selectedSecurityIds)" label="evrak seçildi">
                <span>{{ $screenMode === 'endorse' ? 'Ciro işlemi için hedef cariyi seçin.' : 'Bordro İşlemi bölümünden işlem türünü ve tarihi belirleyin.' }}</span>
            </x-v65.bulk-actions>
        @endif
    </section>

    @endunless

    @if($openedSecurity)
        <section class="panel v65-ref-card v65-record-detail" tabindex="-1" x-init="$nextTick(() => $el.scrollIntoView({ block: 'start', behavior: 'smooth' }))" aria-label="Çek veya senet detayları" id="v65-security-selected-record">
            <header class="v65-ref-card-head"><strong>{{ $openedSecurity->instrument_no }}</strong>
                <small>{{ \App\Support\Formatting\BusinessLabelFormatter::status($openedSecurity->status) }}</small></header>
            <p><strong>Cari:</strong> {{ auth()->user()?->can('contacts.view') ? ($openedSecurity->contact?->title ?? '—') : 'Görüntüleme yetkisi yok' }}</p>
            <p><strong>Vade:</strong> {{ $openedSecurity->due_date?->format('d.m.Y') }}</p>
            <p><strong>Tutar:</strong> {{ \App\Support\Formatting\TrFormatter::money((string) $openedSecurity->amount) }} TRY</p>
            <div class="v65-form-footer">
                @if(in_array($screenMode, ['center','list','create'], true) && in_array($openedSecurity->status, ['portfolio','issued'], true)
                    && $openedSecurity->last_payroll_id === null)
                    @can('securities.update')
                        <x-v65.button variant="secondary" wire:click="prepareSecurityEdit({{ $openedSecurity->id }})">Düzenle</x-v65.button>
                    @endcan
                    @if(auth()->user()?->can('securities.update') && auth()->user()?->can('securities.cancel')
                        && auth()->user()?->can('securities.create'))
                        <x-v65.button variant="secondary" wire:click="prepareFinancialCorrection({{ $openedSecurity->id }})">Mali Düzelt</x-v65.button>
                    @endif
                @endif
                <x-v65.button variant="secondary" wire:click="closeSecurityRecord">Kapat</x-v65.button>
            </div>
        </section>
    @endif

    @if(in_array($screenMode, ['center', 'endorse', 'list'], true))
    <section class="panel stack v65-ref-card v65-ref-security-payroll">
        <header class="v65-ref-card-head"><strong>{{ $screenMode === 'endorse' ? $screenTitle . ' Bilgileri' : 'Bordro İşlemi' }}</strong><small>Kaynak evrak ve işlem detayları</small></header>
        <h2 class="v65-ref-screenreader">{{ $screenMode === 'endorse' ? $screenTitle : 'Bordro İşlemi' }}</h2>
        @if($screenMode !== 'endorse')
        <label class="field">İşlem
            <select wire:model="payrollAction">
                <option value="endorsement">Ciro Et</option>
                <option value="bank_deposit">Bankaya Ver</option>
                <option value="collection">Tahsil Et</option>
                <option value="payment">Öde</option>
                <option value="return">İade</option>
                <option value="protest">Protesto</option>
                <option value="cancel">Verilen Evrakı İptal Et</option>
            </select>
        </label>
        @else
            <p>İşlem: Ciro Et · Portföydeki alınan {{ $screenKind === 'check' ? 'çekler' : 'senetler' }}</p>
        @endif
        @if($screenMode === 'endorse')
            <label class="field">Evrak Ara
                <input type="search" wire:model.live.debounce.300ms="listSearch" placeholder="Evrak no veya cari unvanı" autocomplete="off">
            </label>
            <label class="field">Ciro Edilecek {{ $screenKind === 'check' ? 'Çek' : 'Senet' }} *
                <select wire:model.live="quickEndorseSecurityId">
                    <option value="">Toplu seçim / aşağıdaki listeden seç</option>
                    @foreach($securities as $instrument)
                        @if($instrument->direction === 'incoming' && $instrument->status === 'portfolio')
                            <option value="{{ $instrument->id }}">{{ $instrument->instrument_no }} · {{ $instrument->contact?->title }} · {{ \App\Support\Formatting\TrFormatter::money((string) $instrument->amount) }} TRY</option>
                        @endif
                    @endforeach
                </select>
            </label>
        @endif
        @if($screenMode === 'list')
            <p class="v65-doc-search-hint">Yukarıdaki listeden bir evrak ve işlem seçin. Bordro işlemi burada gerçek veritabanı kaydına dönüşür. Hedef cari veya banka hesabı gerekiyorsa aşağıdan seçin.</p>
            <div class="v65-security-selected">Seçilen evrak: {{ $selectedSecurityIds[0] ?? 'Henüz seçilmedi' }}</div>
        @endif
        <label class="field">{{ $screenMode === 'endorse' ? 'Ciro Tarihi *' : 'İşlem Tarihi' }} <input type="date" wire:model="payrollDate"></label>
        <label class="field">{{ $screenMode === 'endorse' ? 'Ciro Edilen / Hedef Cari *' : 'Hedef Cari' }}
            <input type="search" wire:model.live.debounce.300ms="payrollContactSearch" placeholder="Cari kodu veya unvanı ara" autocomplete="off" aria-label="Ciro hedef cari ara">
            <select wire:model="payrollContactId">
                <option value="">Seçim yok</option>
                @foreach($payrollContacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        @if($screenMode === 'center')
        <label class="field">Banka
            <select wire:model="payrollBankAccountId">
                <option value="">Seçim yok</option>
                @foreach($bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} · {{ $account->account_name }}</option>
                @endforeach
            </select>
        </label>
        @endif
        @if($screenMode === 'endorse')
            <label class="field v65-ref-security-note">Ciro Açıklaması <textarea wire:model.blur="note" rows="2" maxlength="1000" placeholder="Ciro referansı / açıklama"></textarea></label>
            <section class="v65-finance-impact" aria-label="Ciro önizlemesi">
                <h3>Ciro Mali Etkisi <small>İlk evrakı veren/alan carinin etkisi tekrar yazılmaz.</small></h3>
                <div class="v65-impact-grid">
                    <div class="v65-impact-card"><span>Seçilen Evrak Tutarı</span><strong>{{ \App\Support\Formatting\TrFormatter::money($this->selectedPayrollTotal()) }} TRY</strong></div>
                    <div class="v65-impact-card"><span>Hedef Cari Önce</span><strong>{{ $this->canViewSecurityContactBalance() ? \App\Support\Formatting\TrFormatter::money($this->payrollTargetBalance()) . ' TRY' : 'Görüntüleme yetkisi yok' }}</strong></div>
                    <div class="v65-impact-card"><span>Hedef Cari Sonra</span><strong class="{{ bccomp($this->projectedPayrollTargetBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->projectedPayrollTargetBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ $this->canViewSecurityContactBalance() ? \App\Support\Formatting\TrFormatter::money($this->projectedPayrollTargetBalance()) . ' TRY' : 'Görüntüleme yetkisi yok' }}</strong></div>
                    <div class="v65-impact-card"><span>Ciro İşleminde Banka Etkisi</span><strong>0,00 TRY</strong></div>
                </div>
            </section>
        @endif
        <x-v65.button wire:click="postPayroll" wire:loading.attr="disabled" wire:target="postPayroll">{{ $screenMode === 'endorse' ? 'Ciroyu Kaydet' : 'Kaydet' }}</x-v65.button>
    </section>
    @endif

    @if($screenMode === 'center' && $openedPayroll)
        <section class="panel v65-ref-card v65-record-detail" tabindex="-1" x-init="$nextTick(() => $el.scrollIntoView({ block: 'start', behavior: 'smooth' }))" aria-label="Bordro detayları" id="v65-payroll-selected-record">
            <header class="v65-ref-card-head"><strong>Bordro · {{ $openedPayroll->number }}</strong>
                <small>{{ $openedPayroll->payroll_date?->format('d.m.Y') }}</small></header>
            <p><strong>Tutar:</strong> {{ \App\Support\Formatting\TrFormatter::money((string) $openedPayroll->total_amount) }} TRY</p>
            <p><strong>Durum:</strong> {{ \App\Support\Formatting\BusinessLabelFormatter::status($openedPayroll->status) }}</p>
            <div class="v65-form-footer">
                @if($openedPayroll->action !== 'reversal' && $openedPayroll->status === 'posted' && auth()->user()?->can('security_payrolls.cancel'))
                    <x-v65.button variant="danger" wire:click="deletePayroll({{ $openedPayroll->id }})"
                        wire:loading.attr="disabled" wire:target="deletePayroll">Sil</x-v65.button>
                @endif
                <x-v65.button variant="secondary" wire:click="closePayrollRecord">Kapat</x-v65.button>
            </div>
        </section>
    @endif

    @if($screenMode === 'center')
    <section>
        <x-v65.list-heading title="Son Bordrolar" />
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>No</th><th>İşlem</th><th>Tarih</th><th class="align-end">Toplam</th><th class="align-end">Adet</th><th>Durum</th><th>İşlem</th></tr></thead>
            <tbody>
            @foreach($payrolls as $payroll)
                <tr>
                    <td><button type="button" class="link-button v65-record-link" wire:click="openPayrollRecord({{ $payroll->id }})">{{ $payroll->number }}</button></td>
                    <td>{{ \App\Support\Formatting\BusinessLabelFormatter::value('security_action', $payroll->action) }}</td>
                    <td>{{ $payroll->payroll_date?->format('d.m.Y') }}</td>
                    <td class="align-end">{{ $payroll->total_amount }}</td>
                    <td class="align-end">{{ count($payroll->security_ids) }}</td>
                    <td><x-v65.status-badge>{{ \App\Support\Formatting\BusinessLabelFormatter::status($payroll->status) }}</x-v65.status-badge></td>
                    <td><x-v65.button variant="secondary" size="xs" wire:click="openPayrollRecord({{ $payroll->id }})">Aç</x-v65.button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    @endif

    @if($editingSecurityId !== null)
        <div class="v65-contact-transfer-overlay" role="presentation">
            <div class="v65-contact-transfer-dialog" role="dialog" aria-modal="true" aria-labelledby="v65-security-details-title">
                <h3 id="v65-security-details-title">Çek / Senet Belge Bilgileri</h3>
                <p>Yalnız vade ve açıklama düzenlenebilir. Cari, tutar, banka ve evrak numarası muhasebe bütünlüğü için değiştirilmez.</p>
                <label class="field">Vade <input type="date" wire:model="editingDueDate"></label>
                <x-v65.input-error field="editingDueDate"/>
                <label class="field">Açıklama <textarea wire:model="editingNotes" rows="3"></textarea></label>
                <x-v65.input-error field="editingNotes"/>
                <div class="v65-contact-transfer-dialog-actions">
                    <x-v65.button variant="secondary" wire:click="cancelSecurityEdit">Vazgeç</x-v65.button>
                    <x-v65.button wire:click="saveSecurityEdit" wire:loading.attr="disabled" wire:target="saveSecurityEdit">Değişiklikleri Kaydet</x-v65.button>
                </div>
            </div>
        </div>
    @endif

    @if($financialCorrectionId !== null)
        <div class="v65-contact-transfer-overlay" role="presentation">
            <div class="v65-contact-transfer-dialog v65-security-correction-dialog" role="dialog" aria-modal="true" aria-labelledby="v65-financial-correction-title">
                <h3 id="v65-financial-correction-title">Çek / Senet Mali Düzeltme</h3>
                <x-v65.notice tone="warning">Mevcut evrak yerinde değiştirilmeyecek. İlk cari hareketi terslenip yeni evrak güncel tarihle düzenlenecek. Bordro işlemi görmüş evraklar bu yolla değiştirilemez.</x-v65.notice>
                <div class="v65-financial-correction-fields">
                    <label class="field">Evrak No<input wire:model="correctedInstrumentNo" maxlength="80"></label>
                    <label class="field">Cari
                        <input type="search" wire:model.live.debounce.300ms="correctionContactSearch" placeholder="Cari ara" autocomplete="off" aria-label="Mali düzeltme cari ara">
                        <select wire:model="correctedContactId"><option value="">Seçin</option>
                        @foreach($correctionContacts as $contact)<option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>@endforeach
                    </select></label>
                    <label class="field">Tutar (TRY)<input wire:model.blur="correctedAmount" data-tr-decimal inputmode="decimal"></label>
                    <label class="field">Vade Tarihi<input type="date" wire:model="correctedDueDate"></label>
                    <label class="field">Banka Adı<input wire:model="correctedBankName"></label>
                    <label class="field">Banka Hesabı<select wire:model="correctedBankAccountId"><option value="">Seçim yok</option>
                        @foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }}</option>@endforeach
                    </select></label>
                    <label class="field">Düzeltme Gerekçesi<input wire:model.blur="correctionReason" maxlength="1000" required></label>
                    <label class="field">Açıklama<textarea wire:model="correctedNote" rows="2"></textarea></label>
                </div>
                @if($errors->any())
                    <x-v65.notice tone="danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</x-v65.notice>
                @endif
                <div class="v65-contact-transfer-dialog-actions">
                    <x-v65.button variant="secondary" wire:click="cancelFinancialCorrection">Vazgeç</x-v65.button>
                    <x-v65.button variant="danger" wire:click="confirmFinancialCorrection" wire:loading.attr="disabled" wire:target="confirmFinancialCorrection">Tersle ve Yeni Evrak Oluştur</x-v65.button>
                </div>
            </div>
        </div>
    @endif
</div>
