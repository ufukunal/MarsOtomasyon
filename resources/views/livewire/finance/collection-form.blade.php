<div class="space-y-5 panel v65-workspace-form">
    <label class="field">Cari Ara
        <input type="search" wire:model.live.debounce.300ms="contactSearch" placeholder="Cari kodu veya unvanı" autocomplete="off">
    </label>
    <div class="v65-contact-transfer-balance"><span>Seçili cari bakiyesi (Borç − Alacak)</span>
        <strong>{{ \App\Support\Formatting\TrFormatter::money($this->contactBalance()) }} TRY</strong>
    </div>
    @if($correctionSourceId)
        <x-v65.notice tone="warning">#{{ $correctionSourceId }} numaralı kayıt terslendi. Düzeltilen alanları kontrol edip yeni işlem oluşturabilirsiniz.</x-v65.notice>
    @elseif($correctionMode)
        <x-v65.notice tone="warning">#{{ $postedDocumentId }} kesinleşmiş kaydı düzeltme için açıldı. Yeni kayıt oluşturmadan önce ters kayıt onayını tamamlayın.</x-v65.notice>
    @endif
    <h2>Tahsilat</h2>

    <x-v65.select label="Cari" wire:model="contactId" placeholder="Seçin">
            
            @foreach($contacts as $contact)
                <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
            @endforeach
        </x-v65.select>

    <label class="field">Tutar <input wire:model="amount"></label>
    <label class="field">Tarih <input type="date" wire:model="documentDate"></label>

    <x-v65.select label="Hesap türü" wire:model.live="accountType">
            <option value="cash">Kasa</option>
            <option value="bank">Banka</option>
        </x-v65.select>

    <x-v65.select label="Hesap" wire:model="accountId" placeholder="Seçin">
            
            @if($accountType === 'cash')
                @foreach($cashAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>
                @endforeach
            @else
                @foreach($bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} / {{ $account->account_name }}</option>
                @endforeach
            @endif
        </x-v65.select>

    <x-v65.select label="Kaynak fatura (opsiyonel)" wire:model="sourceInvoiceId" placeholder="Yok">
            
            @foreach($invoices as $invoice)
                <option value="{{ $invoice->id }}">{{ $invoice->number }} · {{ $invoice->grand_total }}</option>
            @endforeach
        </x-v65.select>

    <label class="field">Not <textarea wire:model="note"></textarea></label>
    <x-v65.form-actions class="v65-form-footer"><x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post" :disabled="$correctionMode">Tahsilatı Kesinleştir</x-v65.button></x-v65.form-actions>

    @if($postedDocumentId)
        <x-v65.status-badge tone="success">Kesinleşen belge #{{ $postedDocumentId }}</x-v65.status-badge>
        <label class="field">Ters kayıt tarihi <input type="date" wire:model="reversalDate"></label>
        <label class="field">Gerekçe <input wire:model="reversalReason"></label>
        <x-v65.form-actions class="v65-form-footer"><x-v65.button variant="danger" data-v65-modal-open="v65-collection-reversal">Ters Kayıt</x-v65.button></x-v65.form-actions>
    @endif

    @if($postedDocumentId && auth()->user()?->can('collections.cancel'))
        <x-v65.modal id="v65-collection-reversal" title="Ters Kayıt Onayı" size="sm">
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
                            @if(!$hasReverse && auth()->user()?->can('collections'.'.cancel'))
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
