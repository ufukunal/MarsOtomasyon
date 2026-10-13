<div class="space-y-5 panel v65-workspace-form">
    <h2>Tahsilat</h2>

    <label class="field">Cari
        <select wire:model="contactId">
            <option value="">Seçin</option>
            @foreach($contacts as $contact)
                <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
            @endforeach
        </select>
    </label>

    <label class="field">Tutar <input wire:model="amount"></label>
    <label class="field">Tarih <input type="date" wire:model="documentDate"></label>

    <label class="field">Hesap türü
        <select wire:model.live="accountType">
            <option value="cash">Kasa</option>
            <option value="bank">Banka</option>
        </select>
    </label>

    <label class="field">Hesap
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

    <label class="field">Kaynak fatura (opsiyonel)
        <select wire:model="sourceInvoiceId">
            <option value="">Yok</option>
            @foreach($invoices as $invoice)
                <option value="{{ $invoice->id }}">{{ $invoice->number }} · {{ $invoice->grand_total }}</option>
            @endforeach
        </select>
    </label>

    <label class="field">Not <textarea wire:model="note"></textarea></label>
    <div class="v65-form-footer"><button type="button" wire:click="post">Tahsilatı Kesinleştir</button></div>

    @if($postedDocumentId)
        <div>Kesinleşen belge #{{ $postedDocumentId }}</div>
        <label class="field">Ters kayıt tarihi <input type="date" wire:model="reversalDate"></label>
        <label class="field">Gerekçe <input wire:model="reversalReason"></label>
        <div class="v65-form-footer"><button class="button-danger" type="button" wire:click="reverse">Ters Kayıt</button></div>
    @endif
</div>
