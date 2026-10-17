<div class="space-y-5 panel v65-workspace-form">
    <h2>Cari Borç / Alacak Fişi</h2>
    @if($correctionSourceId)
        <div class="alert {{ $correctionReady ? 'alert-success' : 'alert-warning' }}" role="status">
            Cari fiş #{{ $correctionSourceId }} düzeltme için seçildi.
            {{ $correctionReady
                ? 'Ters kayıt tamamlandı; alanları düzenleyip yeni fişi kesinleştirebilirsiniz.'
                : 'Önce eski belgeyi tersleyin; eski kayıt yerinde değiştirilmez.' }}
        </div>
        @unless($correctionReady)
            <label class="field">Ters Kayıt Tarihi <input type="date" wire:model="reversalDate"></label>
            <label class="field">Ters Kayıt Gerekçesi <input wire:model="reversalReason"></label>
            <x-v65.input-error field="reversalDate" />
            <x-v65.input-error field="reversalReason" />
            <div class="v65-form-footer">
                <x-v65.button variant="secondary" wire:click="cancelCorrection">Vazgeç</x-v65.button>
                <x-v65.button variant="danger" wire:click="reverseForCorrection"
                    wire:confirm="Kesinleşmiş cari fiş ters kayda alınacak; mali hareketler silinmeyecek. Devam edilsin mi?">
                    Ters Kayıt ile Düzelt
                </x-v65.button>
            </div>
        @endunless
    @endif

    <label class="field">Cari
        <select wire:model="contactId">
            <option value="">Seçin</option>
            @foreach($contacts as $contact)
                <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
            @endforeach
        </select>
    </label>
    <label class="field">Yön
        <select wire:model="direction">
            <option value="debit">Borç (Debit)</option>
            <option value="credit">Alacak (Credit)</option>
        </select>
    </label>
    <label class="field">Tutar <input wire:model="amount"></label>
    <label class="field">Tarih <input type="date" wire:model="documentDate"></label>
    <label class="field">Gerekçe <input wire:model="reason"></label>
    <label class="field">Not <textarea wire:model="note"></textarea></label>
    <div class="v65-form-footer">
        <x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post"
            :disabled="$correctionSourceId !== null && ! $correctionReady">
            {{ $correctionReady ? 'Düzeltilmiş Fişi Kesinleştir' : 'Kesinleştir' }}
        </x-v65.button>
    </div>

    @if($postedDocumentId)
        <div>Kesinleşen belge #{{ $postedDocumentId }}</div>
    @endif

    <section class="panel" aria-label="Cari fiş geçmişi">
        <h3>Cari Fiş Geçmişi / Düzenleme</h3>
        <p class="v65-doc-search-hint">Kaydı seçtiğinizde eski işlem terslenir, düzeltilen değerlerle yeni fiş oluşturulur.</p>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Belge</th><th>Tarih</th><th>Cari</th><th>Yön</th><th>Tutar (TRY)</th><th>İşlem</th></tr></thead>
            <tbody>
                @forelse($recentDocuments as $record)
                    <tr wire:key="contact-debit-credit-{{ $record->id }}">
                        <td>{{ $record->number ?? '#'.$record->id }}</td>
                        <td>{{ $record->document_date?->format('d.m.Y') }}</td>
                        <td>{{ $record->contact?->title ?? '—' }}</td>
                        <td>{{ $record->contactTransactions->first()?->direction === 'debit' ? 'Borç' : 'Alacak' }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::money((string) $record->grand_total) }}</td>
                        <td>
                            @can('contacts.update')
                                <x-v65.button variant="secondary" size="xs"
                                    wire:click="prepareCorrection({{ $record->id }})">Düzenle</x-v65.button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">Düzeltilebilir fiş bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
</div>
