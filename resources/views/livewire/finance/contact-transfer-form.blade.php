<div class="space-y-5 v65-contact-transfer v65-ref-finance">
<header class="v65-ref-heading"><div><h1>Cari Virman</h1><p>İki cari arasında bakiye devri · Aynı para birimi · Banka ve kasa hareketi oluşturulmaz</p></div><span class="v65-ref-tag">Bakiye esaslı · TRY</span></header>
<nav class="v65-ref-tabs" aria-label="Cari finans ekranları">
<a href="{{ route('finance.collections.create') }}">Tahsilat</a>
<a href="{{ route('purchases.payments.create') }}">Tediye</a>
<a href="{{ route('finance.contact-transfers') }}" aria-current="page">Cari Virman</a>
<a href="{{ route('finance.checks.index') }}">Çek Listesi</a>
<a href="{{ route('finance.notes.index') }}">Senet Listesi</a></nav>
<div class="v65-ref-banner"><strong>Cari bakiyeler arasında aktarım:</strong> Kaynak carinin bakiyesi azalır, hedef caride aynı yönde bakiye artar. Toplam cari bakiye değişmez; banka/kasa etkisi sıfırdır.</div>
    <section class="panel v65-workspace-form v65-ref-card v65-ref-virman-form" aria-label="Virman bilgileri">
        <header class="v65-ref-card-head"><strong>Virman Bilgileri</strong><small>Kaynak ve hedef carileri ayrı ayrı seçin</small></header>
        @if($correctionSourceId)
            <x-v65.notice :tone="$correctionReady ? 'success' : 'warning'">
                #{{ $correctionSourceId }} numaralı virman düzeltme için açıldı.
                @if($correctionReady)
                    Eski virman terslendi; güncel alanları kontrol edip yeni virmanı oluşturabilirsiniz.
                @else
                    Önce ters kayıt onayını tamamlayın. Eski kayıt yerinde değiştirilemez.
                @endif
            </x-v65.notice>
        @endif
        <div class="v65-ref-virman-inputs">
            <div class="v65-ref-virman-contact">
                <label class="field"><span>Kaynak Cari *</span>
                    <input type="search" wire:model.live.debounce.300ms="sourceSearch" placeholder="Cari kodu veya unvanına göre ara..." autocomplete="off">
                </label>
                <label class="field v65-ref-virman-choice"><span>Kaynak cari seçimi</span>
                    <select wire:model.live="sourceContactId"><option value="">Kaynak cari seçin</option>
                        @foreach($sourceOptions as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                        @endforeach
                    </select>
                </label>
                <small>Aktarılacak bakiyenin bulunduğu cari</small><x-v65.input-error field="sourceContactId"/>
            </div>
            <div class="v65-ref-virman-contact">
                <label class="field"><span>Hedef Cari *</span>
                    <input type="search" wire:model.live.debounce.300ms="targetSearch" placeholder="Cari kodu veya unvanına göre ara..." autocomplete="off">
                </label>
                <label class="field v65-ref-virman-choice"><span>Hedef cari seçimi</span>
                    <select wire:model.live="targetContactId"><option value="">Hedef cari seçin</option>
                        @foreach($targetOptions as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                        @endforeach
                    </select>
                </label>
                <small>Bakiyenin aktarılacağı cari</small><x-v65.input-error field="targetContactId"/>
            </div>
            <label class="field"><span>Virman No</span>
                <input readonly value="{{ $postedTransferId ? 'Kayıt #'.$postedTransferId : 'Kaydedilince oluşturulur' }}" aria-label="Virman numarası" />
            </label>
            <label class="field"><span>Virman Tarihi *</span>
                <input type="date" wire:model="documentDate"><x-v65.input-error field="documentDate"/>
            </label>
            <label class="field"><span>Aktarılacak Bakiye Türü</span>
                <select wire:model.live="balanceType"><option value="debit">Borç bakiyesi</option><option value="credit">Alacak bakiyesi</option></select>
            </label>
            <label class="field"><span>Para Birimi</span><input readonly value="TRY · Türk Lirası"></label>
            <label class="field v65-ref-virman-wide"><span>Aktarılacak Tutar *</span>
                <input wire:model.blur="amount" data-tr-decimal inputmode="decimal" placeholder="0,00" aria-invalid="{{ $errors->has('amount') ? 'true' : 'false' }}">
                <x-v65.input-error field="amount"/>
                <small>Aktarılabilir bakiye yalnız seçili kaynak cari üzerinden hesaplanır.</small>
            </label>
            <label class="field v65-ref-virman-wide"><span>Referans / Belge No</span>
                <input wire:model.blur="reference" maxlength="100" placeholder="Opsiyonel işlem / belge no">
                <x-v65.input-error field="reference"/>
            </label>
            <label class="field v65-ref-virman-full"><span>Açıklama / Gerekçe</span>
                <textarea rows="2" wire:model.blur="reason" placeholder="Cari hesaplar arası bakiye virmanı"></textarea>
                <x-v65.input-error field="reason"/>
            </label>
        </div>
        <section class="v65-ref-virman-impact" aria-label="İşlem öncesi ve sonrası bakiyeler">
            <header><div><strong>İşlem Öncesi / Sonrası Bakiyeler</strong><small>Cari borç bakiyesi kırmızı, alacak bakiyesi yeşil gösterilir.</small></div><span>TRY · Bakiye Bazlı</span></header>
            @if($this->canViewContactBalances())
                <div class="v65-ref-virman-balances">
                    <article class="v65-ref-virman-party"><header><span class="v65-ref-virman-number">01</span><div><strong>Kaynak Cari</strong><small>{{ $this->sourceContact()?->title ?: 'Henüz seçilmedi' }}</small></div></header>
<div class="v65-ref-virman-balance-pair"><div><small>Mevcut Bakiye</small>
<strong class="{{ bccomp($this->sourceBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->sourceBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->sourceBalance()) }} TRY</strong></div>
<span aria-hidden="true">→</span><div class="v65-ref-virman-after"><small>Virman Sonrası</small>
<strong class="{{ bccomp($this->projectedSourceBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->projectedSourceBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->projectedSourceBalance()) }} TRY</strong></div></div>
<footer>Bakiye etkisi: {{ $sourceContactId ? 'Tahmini işlem sonrası bakiye' : 'Cari seçilmedi' }}</footer></article>
                    <article class="v65-ref-virman-party"><header><span class="v65-ref-virman-number">02</span><div><strong>Hedef Cari</strong><small>{{ $this->targetContact()?->title ?: 'Henüz seçilmedi' }}</small></div></header>
<div class="v65-ref-virman-balance-pair"><div><small>Mevcut Bakiye</small>
<strong class="{{ bccomp($this->targetBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->targetBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->targetBalance()) }} TRY</strong></div>
<span aria-hidden="true">→</span><div class="v65-ref-virman-after"><small>Virman Sonrası</small>
<strong class="{{ bccomp($this->projectedTargetBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->projectedTargetBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->projectedTargetBalance()) }} TRY</strong></div></div>
<footer>Bakiye etkisi: {{ $targetContactId ? 'Tahmini işlem sonrası bakiye' : 'Cari seçilmedi' }}</footer></article>
                </div>
            @else
                <div class="v65-ref-virman-no-permission">Cari bakiyeleri için görüntüleme yetkisi yok.</div>
            @endif
        </section>
        <div class="v65-ref-virman-totals">
            <div><small>Virman Tutarı</small><strong>{{ is_numeric($amount) ? \App\Support\Formatting\TrFormatter::money((string) $amount) : '0,00' }} TRY</strong></div>
            <div><small>Toplam Cari Bakiye Değişimi</small><strong class="is-creditor">0,00 TRY</strong></div>
            <div><small>Banka / Kasa Etkisi</small><strong>0,00 TRY</strong></div>
        </div>
        @if($postedTransferId)
            <div role="status" class="v65-contact-transfer-notice">Virman kaydedildi. Kayıt #{{ $postedTransferId }}</div>
        @endif
        <p class="v65-ref-virman-help">Pozitif bakiye: <strong class="is-debtor">O Borçlu</strong> · Negatif bakiye: <strong class="is-creditor">O Alacaklı</strong>. Bakiye aşımında yalnız yetkili kullanıcı gerekçeli onay verebilir.</p>
        <div class="v65-ref-virman-footer">
            <small><b>Muhasebe yönü:</b> İki cari hareketi eşit tutarlı, zıt yönlü olarak oluşturulur.</small>
            <div class="v65-ref-virman-actions">
                <x-v65.button variant="secondary" wire:click="clearForm" :disabled="$correctionSourceId !== null">Formu Temizle</x-v65.button>
                <x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post" :disabled="$correctionSourceId !== null && ! $correctionReady">{{ $correctionReady ? 'Düzeltilmiş Virmanı Kaydet' : 'Virmanı Oluştur' }}</x-v65.button>
            </div>
        </div>
    </section>
    <section class="panel v65-workspace-form v65-ref-card v65-ref-history"><header class="v65-ref-card-head"><strong>Cari Virman Geçmişi</strong><small>Yetkili olduğunuz kayıtlar</small></header>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>No</th><th>Tarih</th><th>Kaynak Cari</th><th>Hedef Cari</th><th>Yön</th><th>Referans</th><th>Tutar</th><th>Aşım Onayı</th><th>İşlem</th></tr></thead>
            <tbody>
                @forelse($transfers as $transfer)
                <tr>
                    <td>{{ $transfer->number }}</td>
                    <td>{{ $transfer->transfer_date?->format('d.m.Y') }}</td>
                    <td>{{ $transfer->sourceContact?->title }}</td>
                    <td>{{ $transfer->targetContact?->title }}</td>
                    <td>{{ $transfer->balance_type === 'debit' ? 'Borç Devri' : 'Alacak Devri' }}</td>
                    <td>{{ $transfer->reference ?: '—' }}</td>
                    <td>{{ \App\Support\Formatting\TrFormatter::money((string) $transfer->amount) }} TRY</td>
                    <td>{{ $transfer->overdraft_approved ? 'Onaylı' : '—' }}</td>
                    <td>
                        @if($transfer->reversal_of_id === null && !in_array((int) $transfer->id, $reversedIds, true) && auth()->user()?->can('contact_transfers.cancel'))
                            @if(auth()->user()?->can('contact_transfers.create'))
                                <x-v65.button variant="secondary" size="xs" wire:click="prepareCorrection({{ $transfer->id }})">Düzenle</x-v65.button>
                            @endif
                            <x-v65.button variant="secondary" size="xs" wire:click="prepareReverse({{ $transfer->id }})">Ters Kayıt</x-v65.button>
                        @else
                            <span>—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9">Henüz cari virman kaydı bulunmuyor.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>

    @if($pendingReversal)
        <div class="v65-contact-transfer-overlay" role="presentation">
            <div class="v65-contact-transfer-dialog" role="dialog" aria-modal="true" aria-labelledby="v65-reversal-title">
                <h3 id="v65-reversal-title">Cari Virman — Ters Kayıt Onayı</h3>
                <p>Asıl virman değiştirilmeyecek. İki cari hareketinin tersleri aynı işlemde kaydedilecek.</p>
                @if($correctionSourceId === $pendingReversalId)
                    <p>Ters kayıttan sonra önceki işlem alanları yeni virman için düzenlenebilir kalacaktır.</p>
                @endif
                <p><strong>No:</strong> {{ $pendingReversal->number }}</p>
                <p><strong>Kaynak:</strong> {{ $pendingReversal->sourceContact?->title }}</p>
                <p><strong>Hedef:</strong> {{ $pendingReversal->targetContact?->title }}</p>
                <p><strong>Tutar:</strong> {{ \App\Support\Formatting\TrFormatter::money((string) $pendingReversal->amount) }} TRY</p>
                <label class="field">Ters Kayıt Tarihi <input type="date" wire:model="reversalDate"></label>
                <label class="field">Ters Kayıt Gerekçesi <input wire:model="reversalReason" placeholder="Ters kayıt gerekçesi"></label>
                <x-v65.input-error field="reversalDate"/>
                <x-v65.input-error field="reversalReason"/>
                <div class="v65-contact-transfer-dialog-actions">
                    <x-v65.button variant="secondary" wire:click="cancelReverse">Vazgeç</x-v65.button>
                    <x-v65.button wire:click="confirmReverse" wire:loading.attr="disabled" wire:target="confirmReverse">Ters Kaydı Onayla</x-v65.button>
                </div>
            </div>
        </div>
    @endif

    @if($showOverdraftConfirm)
        <div class="v65-contact-transfer-overlay" role="presentation">
            <div class="v65-contact-transfer-dialog" role="dialog" aria-modal="true" aria-labelledby="v65-contact-overdraft-title">
                <h3 id="v65-contact-overdraft-title">Bakiye Aşımı — Onay Gerekiyor</h3>
                <p>Aktarılacak tutar kaynak carinin mevcut {{ $balanceType === 'debit' ? 'borç' : 'alacak' }} bakiyesini aşamaz.</p>
                <p>Bu işlem ancak bakiye aşımı yetkisiyle ve kayıtlı gerekçeyle yapılabilir.</p>
                <p><strong>Kaynak cari:</strong> {{ $this->sourceContact()?->title }} · <strong>Hedef cari:</strong> {{ $this->targetContact()?->title }}</p>
                <p><strong>İşlem tutarı:</strong> {{ \App\Support\Formatting\TrFormatter::money((string) $amount) }} TRY</p>
                @if(!auth()->user()?->can('contact_transfers.override_balance'))
                    <div class="v65-contact-transfer-error" role="alert">Bu işlem için yetkili kullanıcı onayı gereklidir. Mevcut hesabınızın bakiye aşımı yetkisi yok.</div>
                @endif
                <label class="field"><input type="checkbox" wire:model.live="overdraftAcknowledged">
                    Bakiye aşımını ve işlem sonrası olası ters bakiyeyi onaylıyorum.
                </label>
                <x-v65.input-error field="overdraftAcknowledged"/>
                <div class="v65-contact-transfer-dialog-actions">
                    <x-v65.button variant="secondary" wire:click="cancelOverdraft">Vazgeç</x-v65.button>
                    @if(auth()->user()?->can('contact_transfers.override_balance'))
                        <x-v65.button wire:click="confirmOverdraft" :disabled="!$overdraftAcknowledged">Bakiye Aşımını Onayla ve Kaydet</x-v65.button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
