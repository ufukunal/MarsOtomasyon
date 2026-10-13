<div class="space-y-5 panel v65-workspace-form">
    <h2>Cari Borç / Alacak Fişi</h2>

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
    <div class="v65-form-footer"><button type="button" wire:click="post">Kesinleştir</button></div>

    @if($postedDocumentId)
        <div>Kesinleşen belge #{{ $postedDocumentId }}</div>
    @endif
</div>
