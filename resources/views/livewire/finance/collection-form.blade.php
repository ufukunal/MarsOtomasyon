<div class="space-y-5">
    <h1>Tahsilat</h1>

    <label>Cari
        <select wire:model="contactId">
            <option value="">Seçin</option>
            @foreach($contacts as $contact)
                <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
            @endforeach
        </select>
    </label>

    <label>Tutar <input wire:model="amount"></label>
    <label>Tarih <input type="date" wire:model="documentDate"></label>

    <label>Hesap türü
        <select wire:model.live="accountType">
            <option value="cash">Kasa</option>
            <option value="bank">Banka</option>
        </select>
    </label>

    <label>Hesap
        <select wire:model="accountId">
            <option value="">Seçin</option>
            @if($accountType === 'cash')
                @foreach($cashAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>
                @endforeach
            @else
                @foreach($bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} / {{ $account->account_name }}</option>
                @endforeach
            @endif
        </select>
    </label>

    <label>Kaynak fatura (opsiyonel)
        <select wire:model="sourceInvoiceId">
            <option value="">Yok</option>
            @foreach($invoices as $invoice)
                <option value="{{ $invoice->id }}">{{ $invoice->number }} · {{ $invoice->grand_total }}</option>
            @endforeach
        </select>
    </label>

    <label>Not <textarea wire:model="note"></textarea></label>
    <button type="button" wire:click="post">Tahsilatı Kesinleştir</button>

    @if($postedDocumentId)
        <div>Kesinleşen belge #{{ $postedDocumentId }}</div>
        <label>Ters kayıt tarihi <input type="date" wire:model="reversalDate"></label>
        <label>Gerekçe <input wire:model="reversalReason"></label>
        <button type="button" wire:click="reverse">Ters Kayıt</button>
    @endif
</div>
