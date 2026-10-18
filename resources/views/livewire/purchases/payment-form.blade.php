<div class="space-y-5 v65-workspace-form v65-ref-finance"><header class="v65-ref-heading"><div><h1>Tediye</h1><p>Cari bakiye üzerinden fatura zorunluluğu olmadan ödeme</p></div><span class="v65-ref-tag">Bakiye esaslı · TRY</span></header><nav class="v65-ref-tabs" aria-label="Cari finans ekranları">
<a href="{{ route('finance.collections.create') }}">Tahsilat</a>
<a href="{{ route('purchases.payments.create') }}" aria-current="page">Tediye</a>
<a href="{{ route('finance.contact-transfers') }}">Cari Virman</a>
<a href="{{ route('finance.checks.index') }}">Çek Listesi</a>
<a href="{{ route('finance.notes.index') }}">Senet Listesi</a></nav>
    <section class="panel stack v65-voucher-panel v65-ref-card"><header class="v65-ref-card-head"><strong>Tediye Bilgileri</strong><small>Cari hesabı esas alan kayıt</small></header>
    <label class="field">Cari Ara
        <input type="search" wire:model.live.debounce.300ms="contactSearch" placeholder="Cari kodu veya unvanı" autocomplete="off">
    </label>
    <div class="v65-contact-transfer-balance"><span>Seçili cari bakiyesi (Borç − Alacak)</span>
        <strong>{{ $this->canViewFinancialContactBalance() ? \App\Support\Formatting\TrFormatter::money($this->contactBalance()) . ' TRY' : 'Görüntüleme yetkisi yok' }}</strong>
    </div>
    @if($correctionMode)
        <x-v65.notice tone="info">Kayıt düzenleniyor. Kaydet ile değişiklikler uygulanacak.</x-v65.notice>
    @endif
    <h2 class="v65-ref-screenreader">Ödeme</h2>



        <label class="field">İşlem No <input readonly value="{{ $postedDocumentId ? 'Kayıt #'.$postedDocumentId : 'Kaydedilince atanır' }}"></label>
        <label class="field">Tedarikçi / Cari *
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
        <label class="field">İşlem Tarihi <input type="date" wire:model="documentDate"></label>
        <label class="field">Tediye Tutarı * <input wire:model.blur="amount" inputmode="decimal" data-tr-decimal><x-v65.input-error field="amount" /></label>
        <label class="field">Para Birimi <input wire:model="currency" maxlength="3"></label>
        <label class="field">Kur <input wire:model.blur="exchangeRate" inputmode="decimal" data-tr-decimal><x-v65.input-error field="exchangeRate" /></label>
        <label class="field">Ödeme Yöntemi
            <select wire:model.live="accountType">
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
            </select>
        </label>
        <label class="field">Banka / Kasa Hesabı
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
        <label class="field">Makbuz / Dekont No <input wire:model.blur="reference" maxlength="140" placeholder="Banka dekontu / işlem referansı"></label>
    <label class="field">Açıklama <textarea wire:model="note"></textarea></label>
        <div class="v65-form-footer"><x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post" >Tediyeyi Kaydet</x-v65.button></div>
    </section>
    <section class="panel v65-ref-card v65-ref-summary-card" aria-label="Cari hareket özeti">
        <header class="v65-ref-card-head"><strong>Cari Hareket Özeti</strong><small>Tediye öncesi ve sonrası cari/kasa banka etkisi</small></header>
        <div class="v65-ref-summary-body">
    <div class="v65-ref-impact-title">Cari Hareket Özeti <small>İşlem öncesi ve sonrası bakiye</small></div>
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
        <small>Kaydetme sırasında cari ve kasa/banka tutarları sunucuda yeniden doğrulanır.</small>
    </section>

        </div>
    </section>

    @php
        $openedRecord = $this->selectedFinancialRecord();
        $openedReversed = $this->selectedFinancialRecordIsReversed();
    @endphp
    @if($openedRecord)
        <section class="panel v65-ref-card v65-record-detail" tabindex="-1" x-init="$nextTick(() => $el.scrollIntoView({ block: 'start', behavior: 'smooth' }))" aria-label="Tediye kaydı" id="v65-finance-selected-record">
            <header class="v65-ref-card-head">
                <strong>Tediye · {{ $openedRecord->number ?? '#'.$openedRecord->id }}</strong>
                <small>{{ $openedRecord->document_date?->format('d.m.Y') }}</small>
            </header>
            <p><strong>Tutar:</strong> {{ \App\Support\Formatting\TrFormatter::money((string) $openedRecord->grand_total) }} {{ $openedRecord->currency }}</p>
            <p><strong>Durum:</strong> {{ $openedReversed ? 'İptal edildi' : 'Kaydedildi' }}</p>
            @if($openedRecord->notes)
                <p><strong>Açıklama:</strong> {{ $openedRecord->notes }}</p>
            @endif
            <div class="v65-form-footer">
                @if(! $openedReversed && auth()->user()?->can('payments.cancel') && auth()->user()?->can('payments.create'))
                    <x-v65.button variant="secondary" wire:click="selectForCorrection({{ $openedRecord->id }})">Düzenle</x-v65.button>
                @endif
                @if(! $openedReversed && auth()->user()?->can('payments.cancel'))
                    <x-v65.button variant="danger" wire:click="deleteRecord({{ $openedRecord->id }})"
                        wire:loading.attr="disabled" wire:target="deleteRecord">Sil</x-v65.button>
                @endif
                <x-v65.button variant="secondary" wire:click="closeFinancialRecord">Kapat</x-v65.button>
            </div>
        </section>
    @endif

    <section class="panel v65-financial-history v65-ref-card v65-ref-history">
        <header class="v65-ref-card-head"><strong>Tediye Geçmişi</strong><small>Kaydı açarak inceleyin</small></header>
        <h3 class="v65-ref-screenreader">Kayıtları Aç / Düzenle</h3>
        
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
                            <x-v65.button variant="secondary" size="xs" wire:click="openFinancialRecord({{ $record->id }})">Aç</x-v65.button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Kayıt bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
</div>
