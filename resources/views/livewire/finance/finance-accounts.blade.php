<div class="space-y-6">
    <h1>Kasa / Banka Hesapları</h1>

    <section>
        <h2>Kasa</h2>
        <div>
            <input wire:model="cashCode" placeholder="Kod">
            <input wire:model="cashName" placeholder="Ad">
            <input wire:model="cashCurrency" placeholder="TRY">
            <button type="button" wire:click="saveCash">Kasa Ekle</button>
        </div>
        <table>
            <thead><tr><th>Kod</th><th>Ad</th><th>PB</th><th>Durum</th></tr></thead>
            <tbody>
                @foreach($cashAccounts as $account)
                    <tr><td>{{ $account->code }}</td><td>{{ $account->name }}</td><td>{{ $account->currency }}</td><td>{{ $account->is_active ? 'Aktif' : 'Pasif' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section>
        <h2>Banka</h2>
        <div>
            <input wire:model="bankCode" placeholder="Kod">
            <input wire:model="bankName" placeholder="Banka">
            <input wire:model="bankAccountName" placeholder="Hesap adı">
            <input wire:model="bankIban" placeholder="IBAN">
            <input wire:model="bankCurrency" placeholder="TRY">
            <button type="button" wire:click="saveBank">Banka Ekle</button>
        </div>
        <table>
            <thead><tr><th>Kod</th><th>Banka</th><th>Hesap</th><th>IBAN</th><th>PB</th></tr></thead>
            <tbody>
                @foreach($bankAccounts as $account)
                    <tr><td>{{ $account->code }}</td><td>{{ $account->bank_name }}</td><td>{{ $account->account_name }}</td><td>{{ $account->iban }}</td><td>{{ $account->currency }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>
