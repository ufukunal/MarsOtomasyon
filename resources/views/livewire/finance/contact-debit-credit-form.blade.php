<div class="space-y-5">
    <h1>Cari Borç / Alacak Fişi</h1>

    <label>Cari
        <select wire:model="contactId">
            <option value="">Seçin</option>
            @foreach($contacts as $contact)
                <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
            @endforeach
        </select>
    </label>
    <label>Yön
        <select wire:model="direction">
            <option value="debit">Borç (Debit)</option>
            <option value="credit">Alacak (Credit)</option>
        </select>
    </label>
    <label>Tutar <input wire:model="amount"></label>
    <label>Tarih <input type="date" wire:model="documentDate"></label>
    <label>Gerekçe <input wire:model="reason"></label>
    <label>Not <textarea wire:model="note"></textarea></label>
    <button type="button" wire:click="post">Kesinleştir</button>

    @if($postedDocumentId)
        <div>Kesinleşen belge #{{ $postedDocumentId }}</div>
    @endif
</div>
