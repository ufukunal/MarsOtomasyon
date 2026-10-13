<div class="space-y-6 v65-workspace-form">
    <h2>Ödeme</h2>

    <section class="panel stack v65-voucher-panel">
        <label class="field">Tedarikçi
            <select wire:model="contactId">
                <option value="">Seçin</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">Kaynak Alış Faturası
            <select wire:model="sourceInvoiceId">
                <option value="">Bağımsız ödeme</option>
                @foreach($invoices as $invoice)
                    <option value="{{ $invoice->id }}">{{ $invoice->number }} · {{ $invoice->grand_total }} {{ $invoice->currency }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">Tarih <input type="date" wire:model="documentDate"></label>
        <label class="field">Tutar <input wire:model="amount"></label>
        <label class="field">Para Birimi <input wire:model="currency" maxlength="3"></label>
        <label class="field">Kur <input wire:model="exchangeRate"></label>
        <label class="field">Hesap Türü
            <select wire:model="accountType">
                <option value="cash">Kasa</option>
                <option value="bank">Banka</option>
            </select>
        </label>
        <label class="field">Hesap
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
        <label class="field">Not <textarea wire:model="note"></textarea></label>
        <div class="v65-form-footer"><button type="button" wire:click="post">Ödemeyi Kesinleştir</button></div>
    </section>

    @if($postedDocumentId)
        <section class="panel stack v65-voucher-panel">
            <div>Kesinleşen belge: #{{ $postedDocumentId }}</div>
            <input type="date" wire:model="reversalDate" aria-label="Ters kayıt tarihi">
            <label class="field">Gerekçe <input wire:model="reversalReason" placeholder="Ters kayıt gerekçesi"></label>
            <div class="v65-form-footer"><button class="button-danger" type="button" wire:click="reverse">Ters Kayıt</button></div>
        </section>
    @endif
</div>
