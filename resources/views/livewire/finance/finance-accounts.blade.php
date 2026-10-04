<div class="space-y-6">
    <h1>Kasa / Banka Hesapları</h1>

    <section>
        <h2>Kasa</h2>
        @can('cash_accounts.create')
            <div>
                <input wire:model="cashCode" placeholder="Kod">
                <input wire:model="cashName" placeholder="Ad">
                <input wire:model="cashCurrency" maxlength="3" placeholder="TRY">
                <button type="button" wire:click="saveCash">Kasa Ekle</button>
            </div>
        @endcan

        <table>
            <thead><tr><th>Kod</th><th>Ad</th><th>PB</th><th>Defter Bakiyesi</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            @foreach($cashAccounts as $account)
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->name }}</td>
                    <td>{{ $account->currency }}</td>
                    <td>{{ $account->balance() }}</td>
                    <td>{{ $account->is_active ? 'Aktif' : 'Pasif' }}</td>
                    <td>
                        @can('cash_accounts.update')
                            <button type="button" wire:click="toggleCash({{ $account->id }})">
                                {{ $account->is_active ? 'Pasife Al' : 'Aktifleştir' }}
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section>
        <h2>Banka</h2>
        @can('bank_accounts.create')
            <div>
                <input wire:model="bankCode" placeholder="Kod">
                <input wire:model="bankName" placeholder="Banka">
                <input wire:model="bankAccountName" placeholder="Hesap adı">
                <input wire:model="bankIban" placeholder="IBAN">
                <input wire:model="bankCurrency" maxlength="3" placeholder="TRY">
                <button type="button" wire:click="saveBank">Banka Ekle</button>
            </div>
        @endcan

        <table>
            <thead>
            <tr>
                <th>Kod</th><th>Banka</th><th>Hesap</th><th>IBAN</th><th>PB</th>
                <th>Defter</th><th>Ekstre</th><th>Fark</th><th>Bekleyen</th><th>Durum</th><th></th>
            </tr>
            </thead>
            <tbody>
            @foreach($bankAccounts as $account)
                @php
                    $book = $account->bookBalance();
                    $statement = $account->statementBalance();
                    $difference = $statement === null ? null : bcsub($statement, $book, 4);
                @endphp
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->bank_name }}</td>
                    <td>{{ $account->account_name }}</td>
                    <td>{{ $account->iban }}</td>
                    <td>{{ $account->currency }}</td>
                    <td>{{ $book }}</td>
                    <td>{{ $statement ?? '—' }}</td>
                    <td>{{ $difference ?? '—' }}</td>
                    <td>{{ $account->unreconciledStatementCount() }}</td>
                    <td>{{ $account->is_active ? 'Aktif' : 'Pasif' }}</td>
                    <td>
                        @can('bank_accounts.update')
                            <button type="button" wire:click="toggleBank({{ $account->id }})">
                                {{ $account->is_active ? 'Pasife Al' : 'Aktifleştir' }}
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>
