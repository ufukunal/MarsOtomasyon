<div class="space-y-5 panel v65-workspace-form">
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
    <x-v65.form-actions class="v65-form-footer"><x-v65.button wire:click="post" wire:loading.attr="disabled" wire:target="post">Tahsilatı Kesinleştir</x-v65.button></x-v65.form-actions>

    @if($postedDocumentId)
        <x-v65.status-badge tone="success">Kesinleşen belge #{{ $postedDocumentId }}</x-v65.status-badge>
        <label class="field">Ters kayıt tarihi <input type="date" wire:model="reversalDate"></label>
        <label class="field">Gerekçe <input wire:model="reversalReason"></label>
        <x-v65.form-actions class="v65-form-footer"><x-v65.button variant="danger" wire:click="reverse" wire:loading.attr="disabled" wire:target="reverse">Ters Kayıt</x-v65.button></x-v65.form-actions>
    @endif
</div>
