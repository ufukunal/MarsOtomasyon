<div class="space-y-8 v65-list-page">
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
        <label>Yön
            <select wire:model="direction">
                <option value="incoming">Alınan</option>
                <option value="outgoing">Verilen</option>
            </select>
        </label>
        <label>Tür
            <select wire:model="kind">
                <option value="check">Çek</option>
                <option value="note">Senet</option>
            </select>
        </label>
        @else
            <p>{{ $screenDirection === 'incoming' ? 'Alınan' : 'Verilen' }} {{ $screenKind === 'check' ? 'Çek' : 'Senet' }} kaydı</p>
        @endif
        <label>No <input wire:model="instrumentNo"></label>
        <label>Cari
            <select wire:model="contactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <label>Tutar <input wire:model="amount"></label>
        <label>İşlem Tarihi <input type="date" wire:model="transactionDate"></label>
        <label>Vade <input type="date" wire:model="dueDate"></label>
        <label>Banka Adı <input wire:model="bankName"></label>
        <label>Banka Hesabı
            <select wire:model="bankAccountId">
                <option value="">Seçim yok</option>
                @foreach($bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} · {{ $account->account_name }}</option>
                @endforeach
            </select>
        </label>
        <label>Not <textarea wire:model="note"></textarea></label>
        <x-v65.button wire:click="create" wire:loading.attr="disabled" wire:target="create">Kaydet</x-v65.button>
    </section>
    @endif

    <section>
        <x-v65.list-heading :title="$screenMode === 'center' ? 'Portföy' : $screenTitle . ' Kayıtları'" />
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead>
            <tr><th>Seç</th><th>No</th><th>Tür</th><th>Yön</th><th>Cari</th><th>Vade</th><th class="align-end">Tutar</th><th>Durum</th><th>İşlem</th></tr>
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
                    <td class="align-end">{{ $security->amount }}</td>
                    <td><x-v65.status-badge>{{ \App\Support\Formatting\BusinessLabelFormatter::status($security->status) }}</x-v65.status-badge></td>
                    <td>
                        @if(in_array($screenMode, ['center', 'list', 'create'], true) && auth()->user()?->can('securities.update')
                            && in_array($security->status, ['portfolio', 'issued'], true) && $security->last_payroll_id === null)
                            <x-v65.button size="xs" variant="secondary" wire:click="prepareSecurityEdit({{ $security->id }})">Düzenle</x-v65.button>
                        @else
                            <span>—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @if(in_array($screenMode, ['center', 'endorse'], true))
            <x-v65.bulk-actions :count="count($selectedSecurityIds)" label="evrak seçildi">
                <span>{{ $screenMode === 'endorse' ? 'Ciro işlemi için hedef cariyi seçin.' : 'Bordro İşlemi bölümünden işlem türünü ve tarihi belirleyin.' }}</span>
            </x-v65.bulk-actions>
        @endif
    </section>

    @if(in_array($screenMode, ['center', 'endorse'], true))
    <section class="panel stack">
        <h2>{{ $screenMode === 'endorse' ? $screenTitle : 'Bordro İşlemi' }}</h2>
        @if($screenMode === 'center')
        <label>İşlem
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
        <label>Tarih <input type="date" wire:model="payrollDate"></label>
        <label>Hedef Cari
            <select wire:model="payrollContactId">
                <option value="">Seçim yok</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        @if($screenMode === 'center')
        <label>Banka
            <select wire:model="payrollBankAccountId">
                <option value="">Seçim yok</option>
                @foreach($bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} · {{ $account->account_name }}</option>
                @endforeach
            </select>
        </label>
        @endif
        <x-v65.button wire:click="postPayroll" wire:loading.attr="disabled" wire:target="postPayroll">{{ $screenMode === 'endorse' ? 'Ciroyu Onayla' : 'Bordroyu Kesinleştir' }}</x-v65.button>
    </section>
    @endif

    @if($screenMode === 'center')
    <section class="panel stack">
        <h2>Bordro Ters Kayıt</h2>
        <label>Bordro
            <select wire:model="reversePayrollId">
                <option value="">Seçin</option>
                @foreach($payrolls as $payroll)
                    @if($payroll->action !== 'reversal')
                        <option value="{{ $payroll->id }}">{{ $payroll->number }} · {{ \App\Support\Formatting\BusinessLabelFormatter::value('security_action', $payroll->action) }} · {{ $payroll->total_amount }}</option>
                    @endif
                @endforeach
            </select>
        </label>
        <label>Tarih <input type="date" wire:model="reversalDate"></label>
        <label>Gerekçe <input wire:model="reversalReason"></label>
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
</div>
