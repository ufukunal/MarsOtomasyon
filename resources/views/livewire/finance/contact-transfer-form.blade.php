<div class="space-y-5 v65-contact-transfer">
    <section class="panel v65-workspace-form">
        <h2>Cari Virman</h2>
        <p class="v65-doc-search-hint">Cari bakiyesi esaslı virman. Kasa/banka hareketi oluşturmaz. Kayıtlar TRY cinsindendir.</p>
        <div class="v65-contact-transfer-grid">
            <div class="v65-contact-transfer-party">
                <strong>Kaynak Cari</strong>
                <label class="field"><span>Cari kodu / ünvan ara</span>
                    <input type="search" wire:model.live.debounce.300ms="sourceSearch" placeholder="Kaynak cari ara">
                </label>
                <label class="field"><span>Cari seç</span>
                    <select wire:model.live="sourceContactId">
                        <option value="">Kaynak cari seçin</option>
                        @foreach($sourceOptions as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="v65-contact-transfer-balance"><span>Mevcut bakiye (Borç − Alacak)</span>
                    <strong class="{{ bccomp($this->sourceBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->sourceBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->sourceBalance()) }} TRY</strong>
                </div>
                <div class="v65-contact-transfer-balance"><span>İşlem Sonrası (Taslak)</span>
                    <strong class="{{ bccomp($this->projectedSourceBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->projectedSourceBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->projectedSourceBalance()) }} TRY</strong>
                </div>
            </div>
            <div class="v65-contact-transfer-party">
                <strong>Hedef Cari</strong>
                <label class="field"><span>Cari kodu / ünvan ara</span>
                    <input type="search" wire:model.live.debounce.300ms="targetSearch" placeholder="Hedef cari ara">
                </label>
                <label class="field"><span>Cari seç</span>
                    <select wire:model.live="targetContactId">
                        <option value="">Hedef cari seçin</option>
                        @foreach($targetOptions as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="v65-contact-transfer-balance"><span>Mevcut bakiye (Borç − Alacak)</span>
                    <strong class="{{ bccomp($this->targetBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->targetBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->targetBalance()) }} TRY</strong>
                </div>
                <div class="v65-contact-transfer-balance"><span>İşlem Sonrası (Taslak)</span>
                    <strong class="{{ bccomp($this->projectedTargetBalance(), '0', 4) > 0 ? 'is-debtor' : (bccomp($this->projectedTargetBalance(), '0', 4) < 0 ? 'is-creditor' : '') }}">{{ \App\Support\Formatting\TrFormatter::money($this->projectedTargetBalance()) }} TRY</strong>
                </div>
            </div>
        </div>
        <div class="v65-contact-transfer-controls">
            <label class="field"><span>Virman Yönü</span>
                <select wire:model.live="balanceType">
                    <option value="debit">Borç bakiyesi devri</option>
                    <option value="credit">Alacak bakiyesi devri</option>
                </select>
            </label>
            <label class="field"><span>Virman Tutarı (TRY)</span>
                <input wire:model.blur="amount" data-tr-decimal inputmode="decimal" aria-invalid="{{ $errors->has('amount') ? 'true' : 'false' }}">
                <x-v65.input-error field="amount"/>
            </label>
            <label class="field"><span>İşlem Tarihi</span>
                <input type="date" wire:model="documentDate">
            </label>
            <label class="field"><span>Gerekçe / Açıklama</span>
                <input wire:model.blur="reason" placeholder="Virmanın nedenini yazın">
                <x-v65.input-error field="reason"/>
            </label>
        </div>
        @if($postedTransferId)
            <div role="status" class="v65-contact-transfer-notice">Virman kaydedildi. Kayıt #{{ $postedTransferId }}</div>
        @endif
        <p class="v65-doc-search-hint">Pozitif bakiye: <strong class="is-debtor">O Borçlu</strong> · Negatif bakiye: <strong class="is-creditor">O Alacaklı</strong>. Bakiye aşımlarında yalnız yetkili kullanıcı açık onay ve gerekçeyle işlem yapabilir.</p>
        <div class="v65-form-footer">
            <x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post">Virmanı Oluştur</x-v65.button>
        </div>
    </section>
    <section class="panel v65-workspace-form">
        <h3>Cari Virman Geçmişi</h3>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>No</th><th>Tarih</th><th>Kaynak Cari</th><th>Hedef Cari</th><th>Yön</th><th>Tutar</th><th>Aşım Onayı</th><th>İşlem</th></tr></thead>
            <tbody>
                @forelse($transfers as $transfer)
                <tr>
                    <td>{{ $transfer->number }}</td>
                    <td>{{ $transfer->transfer_date?->format('d.m.Y') }}</td>
                    <td>{{ $transfer->sourceContact?->title }}</td>
                    <td>{{ $transfer->targetContact?->title }}</td>
                    <td>{{ $transfer->balance_type === 'debit' ? 'Borç Devri' : 'Alacak Devri' }}</td>
                    <td>{{ \App\Support\Formatting\TrFormatter::money((string) $transfer->amount) }} TRY</td>
                    <td>{{ $transfer->overdraft_approved ? 'Onaylı' : '—' }}</td>
                    <td>
                        @if($transfer->reversal_of_id === null && !in_array((int) $transfer->id, $reversedIds, true) && auth()->user()?->can('contact_transfers.cancel'))
                            <x-v65.button variant="secondary" size="xs" wire:click="prepareReverse({{ $transfer->id }})">Ters Kayıt</x-v65.button>
                        @else
                            <span>—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8">Henüz cari virman kaydı bulunmuyor.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>

    @if($pendingReversal)
        <div class="v65-contact-transfer-overlay" role="presentation">
            <div class="v65-contact-transfer-dialog" role="dialog" aria-modal="true" aria-labelledby="v65-reversal-title">
                <h3 id="v65-reversal-title">Cari Virman — Ters Kayıt Onayı</h3>
                <p>Asıl virman değiştirilmeyecek. İki cari hareketinin tersleri aynı işlemde kaydedilecek.</p>
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
