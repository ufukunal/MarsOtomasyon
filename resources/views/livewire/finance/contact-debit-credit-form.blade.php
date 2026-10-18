<div class="space-y-5 panel v65-workspace-form">
    <h2>Cari Borç / Alacak Fişi</h2>
    @if($correctionSourceId)
        <x-v65.notice tone="info">Cari fişi düzenleniyor. Değişiklikleri kaydettiğinizde önceki mali hareket otomatik olarak dengelenecek.</x-v65.notice>
        <x-v65.button variant="secondary" wire:click="cancelCorrection">Vazgeç</x-v65.button>
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
        <x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post">Kaydet</x-v65.button>
    </div>

    @if($postedDocumentId)
        <div>Kaydedilen fiş #{{ $postedDocumentId }}</div>
    @endif

    @if($selectedRecord)
        <section class="panel v65-record-detail" tabindex="-1" x-init="$nextTick(() => $el.scrollIntoView({ block: 'start', behavior: 'smooth' }))" aria-label="Cari fişi detayları" id="v65-contact-selected-record">
            <h3>Cari Fişi · {{ $selectedRecord->number ?? '#'.$selectedRecord->id }}</h3>
            <p><strong>Cari:</strong> {{ auth()->user()?->can('contacts.view') ? ($selectedRecord->contact?->title ?? '—') : 'Görüntüleme yetkisi yok' }}</p>
            <p><strong>Tarih:</strong> {{ $selectedRecord->document_date?->format('d.m.Y') }}</p>
            <p><strong>Tutar:</strong> {{ \App\Support\Formatting\TrFormatter::money((string) $selectedRecord->grand_total) }} TRY</p>
            <div class="v65-form-footer">
                <x-v65.button variant="secondary" wire:click="prepareCorrection({{ $selectedRecord->id }})">Düzenle</x-v65.button>
                <x-v65.button variant="secondary" wire:click="closeRecord">Kapat</x-v65.button>
            </div>
            <x-v65.record-delete-footer>
                <x-v65.button variant="danger" wire:click="requestRecordDeletion('deleteRecord', [{{ $selectedRecord->id }}])"
                    wire:loading.attr="disabled" wire:target="requestRecordDeletion">Sil</x-v65.button>
            </x-v65.record-delete-footer>
        </section>
    @endif

    <section class="panel" aria-label="Cari fiş geçmişi">
        <h3>Cari Fiş Geçmişi / Düzenleme</h3>
        <p class="v65-doc-search-hint">Kaydı seçip alanları düzenleyin; Kaydet ile değişiklikleri uygulayın.</p>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Belge</th><th>Tarih</th><th>Cari</th><th>Yön</th><th>Tutar (TRY)</th><th>İşlem</th></tr></thead>
            <tbody>
                @forelse($recentDocuments as $record)
                    <tr wire:key="contact-debit-credit-{{ $record->id }}">
                        <td><button type="button" class="link-button v65-record-link" wire:click="openRecord({{ $record->id }})">{{ $record->number ?? '#'.$record->id }}</button></td>
                        <td>{{ $record->document_date?->format('d.m.Y') }}</td>
                        <td>{{ $record->contact?->title ?? '—' }}</td>
                        <td>{{ $record->contactTransactions->first()?->direction === 'debit' ? 'Borç' : 'Alacak' }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::money((string) $record->grand_total) }}</td>
                        <td><x-v65.button variant="secondary" size="xs" wire:click="openRecord({{ $record->id }})">Aç</x-v65.button></td>
                    </tr>
                @empty
                    <tr><td colspan="6">Düzeltilebilir fiş bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
    @include('livewire.partials.record-delete-confirmation')
</div>
