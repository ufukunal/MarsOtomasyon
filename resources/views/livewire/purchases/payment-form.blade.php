<div class="space-y-5 v65-workspace-form">
    <section class="panel stack v65-voucher-panel">
    <label class="field">Cari Ara
        <input type="search" wire:model.live.debounce.300ms="contactSearch" placeholder="Cari kodu veya unvanı" autocomplete="off">
    </label>
    <div class="v65-contact-transfer-balance"><span>Seçili cari bakiyesi (Borç − Alacak)</span>
        <strong>{{ $this->canViewFinancialContactBalance() ? \App\Support\Formatting\TrFormatter::money($this->contactBalance()) . ' TRY' : 'Görüntüleme yetkisi yok' }}</strong>
    </div>
    @if($correctionSourceId)
        <x-v65.notice tone="warning">#{{ $correctionSourceId }} numaralı kayıt terslendi. Düzeltilen alanları kontrol edip yeni işlem oluşturabilirsiniz.</x-v65.notice>
    @elseif($correctionMode)
        <x-v65.notice tone="warning">#{{ $postedDocumentId }} kesinleşmiş kaydı düzeltme için açıldı. Yeni kayıt oluşturmadan önce ters kayıt onayını tamamlayın.</x-v65.notice>
    @endif
    <h2>Ödeme</h2>
    <section class="v65-finance-impact" aria-label="İşlem öncesi ve sonrası mali etki">
        <h3>İşlem Etkisi <small>Bilgilendirme amaçlı önizleme</small></h3>
        <div class="v65-impact-grid">
            <div class="v65-impact-card"><span>Cari Önce (Borç − Alacak)</span>
                <strong class="{{ bccomp($this->contactBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->contactBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ $this->canViewFinancialContactBalance() ? \App\Support\Formatting\TrFormatter::money($this->contactBalance()) . ' TRY' : 'Görüntüleme yetkisi yok' }}</strong></div>
            <div class="v65-impact-card"><span>Cari Sonra (Tahmini)</span>
                <strong class="{{ bccomp($this->projectedFinancialContactBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->projectedFinancialContactBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ $this->canViewFinancialContactBalance() ? \App\Support\Formatting\TrFormatter::money($this->projectedFinancialContactBalance()) . ' TRY' : 'Görüntüleme yetkisi yok' }}</strong></div>
            <div class="v65-impact-card"><span>Kasa / Banka Önce (Defter)</span>
                <strong>{{ ! $this->canViewFinancialAccountBalance() ? 'Görüntüleme yetkisi yok' : ($this->selectedFinancialAccount() === null ? 'Hesap seçin' : \App\Support\Formatting\TrFormatter::money($this->financialAccountBalance()) . ' ' . $this->financialAccountCurrency()) }}</strong></div>
            <div class="v65-impact-card"><span>Kasa / Banka Sonra (Tahmini)</span>
                <strong>{{ $this->projectedFinancialAccountBalance() === null ? ($this->canViewFinancialAccountBalance() ? ($this->selectedFinancialAccount() === null ? 'Hesap seçin' : 'Para birimi uyuşmuyor') : 'Görüntüleme yetkisi yok') : \App\Support\Formatting\TrFormatter::money($this->projectedFinancialAccountBalance()) . ' ' . $this->financialAccountCurrency() }}</strong></div>
        </div>
        @unless($this->financialAccountCurrencyMatches())
            <x-v65.notice tone="danger">Seçilen kasa/banka hesabının para birimi işlem para biriminden farklıdır.</x-v65.notice>
        @endunless
        <small>Kesinleştirme sırasında cari ve kasa/banka tutarları sunucuda yeniden doğrulanır.</small>
    </section>


        <label class="field">Tedarikçi
            <select wire:model.live="contactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">Kaynak Alış Faturası
            <select wire:model="sourceInvoiceId">
                <option value="">Bağımsız ödeme</option>
                @foreach($invoices as $invoice)
                    <option value="{{ $invoice->id }}">{{ $invoice->number }} · {{ $invoice->grand_total }} {{ $invoice->currency }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">Tarih <input type="date" wire:model="documentDate"></label>
        <label class="field">Tutar <input wire:model.blur="amount" inputmode="decimal" data-tr-decimal><x-v65.input-error field="amount" /></label>
        <label class="field">Para Birimi <input wire:model="currency" maxlength="3"></label>
        <label class="field">Kur <input wire:model.blur="exchangeRate" inputmode="decimal" data-tr-decimal><x-v65.input-error field="exchangeRate" /></label>
        <label class="field">Hesap Türü
            <select wire:model.live="accountType">
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
            </select>
        </label>
        <label class="field">Hesap
            <select wire:key="account-{{ $accountType }}" wire:model="accountId">
                <option value="">Seçin</option>
                @if($accountType === 'cash')
                    @foreach($cashAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }} · {{ $account->currency }}</option>
                    @endforeach
                @else
                    @foreach($bankAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->bank_name }} · {{ $account->account_name }} · {{ $account->currency }}</option>
                    @endforeach
                @endif
            </select>
        </label>
        <label class="field">Dekont / Referans No <input wire:model.blur="reference" maxlength="140" placeholder="Banka dekontu / işlem referansı"></label>
    <label class="field">Not <textarea wire:model="note"></textarea></label>
        <div class="v65-form-footer"><x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post" :disabled="$correctionMode">Tediyeyi Kesinleştir</x-v65.button></div>
    </section>

    @if($postedDocumentId)
        <section class="panel stack v65-voucher-panel">
            <div>Kesinleşen belge: #{{ $postedDocumentId }}</div>
            <input type="date" wire:model="reversalDate" aria-label="Ters kayıt tarihi">
            <label class="field">Gerekçe <input wire:model="reversalReason" placeholder="Ters kayıt gerekçesi"></label>
            <div class="v65-form-footer"><x-v65.button variant="danger" data-v65-modal-open="v65-payment-reversal">Ters Kayıt</x-v65.button></div>
        </section>
    @endif

    @if($postedDocumentId && auth()->user()?->can('payments.cancel'))
        <x-v65.modal id="v65-payment-reversal" title="Ters Kayıt Onayı" size="sm">
            <p>#{{ $postedDocumentId }} kesinleşmiş finansal belgesi ters kaydedilecek. Bu işlem kasa/banka ve cari hesaplarda karşıt hareket oluşturur.</p>
            <p>İşlem tarihi ve düzeltme gerekçesini kontrol edin.</p>
            <x-slot:footer>
                <x-v65.button variant="secondary" data-v65-modal-close>Vazgeç</x-v65.button>
                <x-v65.button variant="danger" wire:click="reverse" wire:loading.attr="disabled" wire:target="reverse" data-v65-modal-close>Onayla ve Tersle</x-v65.button>
            </x-slot:footer>
        </x-v65.modal>
    @endif

    <section class="panel v65-financial-history">
        <h3>Kayıtları Aç / Düzenle</h3>
        <p class="v65-doc-search-hint">Kesinleşmiş hareketler doğrudan değiştirilmez. Kaydı seçin, ters kayıt onayı verin, ardından düzeltilmiş işlemi yeniden kaydedin.</p>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Belge</th><th>Tarih</th><th>Tutar</th><th>Durum</th><th>İşlem</th></tr></thead>
            <tbody>
                @forelse($recentRecords as $record)
                    @php $hasReverse = in_array((int) $record->id, $reversedIds, true); @endphp
                    <tr>
                        <td>{{ $record->number ?? '#'.$record->id }}</td>
                        <td>{{ $record->document_date->format('d.m.Y') }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::money((string) $record->grand_total) }} {{ $record->currency }}</td>
                        <td>{{ $hasReverse ? 'Terslendi' : 'Kesinleşti' }}</td>
                        <td>
                            @if(!$hasReverse && auth()->user()?->can('payments'.'.cancel'))
                                <x-v65.button variant="secondary" size="xs" wire:click="selectForCorrection({{ $record->id }})">Düzenle</x-v65.button>
                            @else
                                <span>—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Kayıt bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
</div>
