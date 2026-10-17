<div class="space-y-8 v65-list-page">
    @if($replacementSecurityId)
        <x-v65.notice tone="success">Mali düzeltme tamamlandı. Eski evrak terslendi; yeni evrak #{{ $replacementSecurityId }} oluşturuldu.</x-v65.notice>
    @endif
    @if($screenMode !== 'center')
        <section class="panel stack">
            <h2>{{ $screenTitle }}</h2>
            <nav class="v65-security-nav" aria-label="Çek ve senet işlemleri">
                @foreach([
                    'finance.checks.index' => 'Çek Listesi', 'finance.checks.incoming' => 'Alınan Çek',
                    'finance.checks.outgoing' => 'Verilen Çek', 'finance.checks.endorsement' => 'Çek Ciro',
                    'finance.notes.index' => 'Senet Listesi', 'finance.notes.incoming' => 'Alınan Senet',
                    'finance.notes.outgoing' => 'Verilen Senet', 'finance.notes.endorsement' => 'Senet Ciro'
                ] as $route => $label)
                    <a href="{{ route($route) }}" class="v65-security-nav-link">{{ $label }}</a>
                @endforeach
            </nav>
        </section>
    @endif

    @if(in_array($screenMode, ['center', 'create'], true))
    <section class="panel stack">
        <h2>{{ $screenMode === 'center' ? 'Yeni Çek / Senet' : $screenTitle }}</h2>
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
        <label class="field">No <input wire:model.blur="instrumentNo"></label>
        <label class="field">{{ ($screenKind ?? $kind) === 'note' ? 'Borçlu / Lehtar (Cari)' : 'Cari' }}
            <input type="search" wire:model.live.debounce.300ms="securityContactSearch" placeholder="Cari kodu veya unvanı ara" autocomplete="off" aria-label="Evrak cari ara">
            <select wire:model="contactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">Tutar <input wire:model.blur="amount" inputmode="decimal" data-tr-decimal></label>
        <label class="field">İşlem Tarihi <input type="date" wire:model="transactionDate"></label>
        <label class="field">Vade <input type="date" wire:model="dueDate"></label>
        <label class="field">Banka Adı <input wire:model="bankName"></label>
        <label class="field">Banka Hesabı
            <select wire:model="bankAccountId">
                <option value="">Seçim yok</option>
                @foreach($bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} · {{ $account->account_name }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">Not <textarea wire:model.blur="note" rows="3"></textarea></label>
        <section class="v65-finance-impact" aria-label="Evrak ilk kayıt önizlemesi">
            <h3>İlk Kayıt Mali Etkisi <small>Banka hareketi ancak tahsil/ödeme bordrosunda oluşur.</small></h3>
            <div class="v65-impact-grid">
                <div class="v65-impact-card"><span>İlgili Cari Önce (Borç − Alacak)</span>
                    <strong class="{{ bccomp($this->selectedContactBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->selectedContactBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->selectedContactBalance()) }} TRY</strong></div>
                <div class="v65-impact-card"><span>İlgili Cari Sonra (Tahmini)</span>
                    <strong class="{{ bccomp($this->projectedContactBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->projectedContactBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->projectedContactBalance()) }} TRY</strong></div>
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

    <section>
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
            <tr><th>Seç</th><th>No</th><th>Tür</th><th>Yön</th><th>Cari</th><th>Vade</th><th class="align-end">Tutar</th><th class="align-end">Cari Etkisi (İlk Kayıt)</th><th>Durum</th><th>İşlem</th></tr>
            </thead>
            <tbody>
            @foreach($securities as $security)
                <tr>
                    <td>
                        @if(in_array($screenMode, ['center', 'endorse'], true) && ($screenMode !== 'endorse' || ($security->direction === 'incoming' && $security->status === 'portfolio')))
                            <input type="checkbox" wire:model="selectedSecurityIds" value="{{ $security->id }}">
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $security->instrument_no }}</td>
                    <td>{{ \App\Support\Formatting\BusinessLabelFormatter::value('security_kind', $security->kind) }}</td>
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
                        @if(in_array($screenMode, ['center', 'list', 'create'], true) && auth()->user()?->can('securities.update')
                            && in_array($security->status, ['portfolio', 'issued'], true) && $security->last_payroll_id === null)
                            <x-v65.button size="xs" variant="secondary" wire:click="prepareSecurityEdit({{ $security->id }})">Düzenle</x-v65.button>
                        @else
                            <span>—</span>
                        @endif
                        @if(in_array($screenMode, ['center', 'list', 'create'], true)
                            && in_array($security->status, ['portfolio', 'issued'], true)
                            && $security->last_payroll_id === null
                            && auth()->user()?->can('securities.update')
                            && auth()->user()?->can('securities.cancel')
                            && auth()->user()?->can('securities.create'))
                            <x-v65.button variant="secondary" size="xs" wire:click="prepareFinancialCorrection({{ $security->id }})">Mali Düzelt</x-v65.button>
                        @endif
                        @if($screenMode === 'list' && auth()->user()?->can('security_payrolls.create'))
                            <span class="v65-security-row-actions">
                                @if($security->direction === 'incoming' && $security->status === 'portfolio')
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'endorsement')">Ciro</x-v65.button>
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'bank_deposit')">Bankaya Ver</x-v65.button>
                                @endif
                                @if($security->direction === 'incoming' && in_array($security->status, ['portfolio', 'banked'], true))
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'collection')">Tahsil</x-v65.button>
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'return')">İade</x-v65.button>
                                @endif
                                @if($security->direction === 'outgoing' && $security->status === 'issued')
                                    <x-v65.button size="xs" variant="secondary" wire:click="prepareListPayroll({{ $security->id }}, 'payment')">Öde</x-v65.button>
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

    @if(in_array($screenMode, ['center', 'endorse', 'list'], true))
    <section class="panel stack">
        <h2>{{ $screenMode === 'endorse' ? $screenTitle : 'Bordro İşlemi' }}</h2>
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
            <label class="field">Tek Evrak Seç (İsteğe Bağlı)
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
        <label class="field">Tarih <input type="date" wire:model="payrollDate"></label>
        <label class="field">Hedef Cari
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
            <section class="v65-finance-impact" aria-label="Ciro önizlemesi">
                <h3>Ciro Mali Etkisi <small>İlk evrakı veren/alan carinin etkisi tekrar yazılmaz.</small></h3>
                <div class="v65-impact-grid">
                    <div class="v65-impact-card"><span>Seçilen Evrak Tutarı</span><strong>{{ \App\Support\Formatting\TrFormatter::money($this->selectedPayrollTotal()) }} TRY</strong></div>
                    <div class="v65-impact-card"><span>Hedef Cari Önce</span><strong>{{ \App\Support\Formatting\TrFormatter::money($this->payrollTargetBalance()) }} TRY</strong></div>
                    <div class="v65-impact-card"><span>Hedef Cari Sonra</span><strong class="{{ bccomp($this->projectedPayrollTargetBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->projectedPayrollTargetBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->projectedPayrollTargetBalance()) }} TRY</strong></div>
                    <div class="v65-impact-card"><span>Ciro İşleminde Banka Etkisi</span><strong>0,00 TRY</strong></div>
                </div>
            </section>
        @endif
        <x-v65.button wire:click="postPayroll" wire:loading.attr="disabled" wire:target="postPayroll">{{ $screenMode === 'endorse' ? 'Ciroyu Onayla' : 'Bordroyu Kesinleştir' }}</x-v65.button>
    </section>
    @endif

    @if($screenMode === 'center')
    <section class="panel stack">
        <h2>Bordro Ters Kayıt</h2>
        <label class="field">Bordro
            <select wire:model="reversePayrollId">
                <option value="">Seçin</option>
                @foreach($payrolls as $payroll)
                    @if($payroll->action !== 'reversal')
                        <option value="{{ $payroll->id }}">{{ $payroll->number }} · {{ \App\Support\Formatting\BusinessLabelFormatter::value('security_action', $payroll->action) }} · {{ $payroll->total_amount }}</option>
                    @endif
                @endforeach
            </select>
        </label>
        <label class="field">Tarih <input type="date" wire:model="reversalDate"></label>
        <label class="field">Gerekçe <input wire:model="reversalReason"></label>
        <x-v65.button variant="danger" data-v65-modal-open="v65-security-reversal-confirm">Bordroyu Tersle</x-v65.button>
    </section>

    <section>
        <x-v65.list-heading title="Son Bordrolar" />
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>No</th><th>İşlem</th><th>Tarih</th><th class="align-end">Toplam</th><th class="align-end">Adet</th><th>Terslenen</th><th>Durum</th></tr></thead>
            <tbody>
            @foreach($payrolls as $payroll)
                <tr>
                    <td>{{ $payroll->number }}</td>
                    <td>{{ \App\Support\Formatting\BusinessLabelFormatter::value('security_action', $payroll->action) }}</td>
                    <td>{{ $payroll->payroll_date?->format('d.m.Y') }}</td>
                    <td class="align-end">{{ $payroll->total_amount }}</td>
                    <td class="align-end">{{ count($payroll->security_ids) }}</td>
                    <td>{{ $payroll->reversal_of_id ?? '—' }}</td>
                    <td><x-v65.status-badge>{{ \App\Support\Formatting\BusinessLabelFormatter::status($payroll->status) }}</x-v65.status-badge></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <x-v65.modal id="v65-security-reversal-confirm" title="Bordroyu Ters Kaydet" size="sm">
        <p>Seçilen bordro için ters kayıt oluşturulacaktır. İşlem tarihi ve gerekçeyi kontrol edin.</p>
        <p><strong>Bordro:</strong> {{ $reversePayrollId ?: 'Seçilmedi' }}</p>
        <p><strong>Gerekçe:</strong> {{ $reversalReason ?: 'Girilmedi' }}</p>
        <x-slot:footer>
            <x-v65.button variant="secondary" data-v65-modal-close>Vazgeç</x-v65.button>
            <x-v65.button variant="danger" wire:click="reversePayroll" wire:loading.attr="disabled" wire:target="reversePayroll" data-v65-modal-close>Ters Kaydı Onayla</x-v65.button>
        </x-slot:footer>
    </x-v65.modal>
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
