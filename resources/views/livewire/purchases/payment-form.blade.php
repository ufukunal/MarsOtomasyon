<div class="space-y-6">
    <h1>Ödeme</h1>

    <section class="panel stack v65-voucher-panel">
        <label>Tedarikçi
            <select wire:model="contactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <label>Kaynak Alış Faturası
            <select wire:model="sourceInvoiceId">
                <option value="">Bağımsız ödeme</option>
                @foreach($invoices as $invoice)
                    <option value="{{ $invoice->id }}">{{ $invoice->number }} · {{ $invoice->grand_total }} {{ $invoice->currency }}</option>
                @endforeach
            </select>
        </label>
        <label>Tarih <input type="date" wire:model="documentDate"></label>
        <label>Tutar <input wire:model="amount"></label>
        <label>Para Birimi <input wire:model="currency" maxlength="3"></label>
        <label>Kur <input wire:model="exchangeRate"></label>
        <label>Hesap Türü
            <select wire:model="accountType">
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
            </select>
        </label>
        <label>Hesap
            <select wire:model="accountId">
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
        <label>Not <textarea wire:model="note"></textarea></label>
        <button type="button" wire:click="post">Ödemeyi Kesinleştir</button>
    </section>

    @if($postedDocumentId)
        <section class="panel stack v65-voucher-panel">
            <div>Kesinleşen belge: #{{ $postedDocumentId }}</div>
            <input type="date" wire:model="reversalDate" aria-label="Ters kayıt tarihi">
            <input wire:model="reversalReason" placeholder="Ters kayıt gerekçesi">
            <button class="button-danger" type="button" wire:click="reverse">Ters Kayıt</button>
        </section>
    @endif
</div>
