<div class="space-y-6 v65-list-page">

    <section class="panel stack v65-workspace-form">
        <h2>Kasa</h2>
        @can('cash_accounts.create')
            <div class="v65-inline-action-form">
                <input wire:model="cashCode" placeholder="Kod">
                <input wire:model="cashName" placeholder="Ad">
                <input wire:model="cashCurrency" maxlength="3" placeholder="TRY">
                <button type="button" wire:click="saveCash">{{ $cashEditId ? 'Kasayı Güncelle' : 'Kasa Ekle' }}</button>
                @if($cashEditId)
                    <button class="button-secondary" type="button" wire:click="cancelCashEdit">Vazgeç</button>
                @endif
            </div>
        @endcan

        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>Kod</th><th>Ad</th><th>PB</th><th>Defter Bakiyesi</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            @foreach($cashAccounts as $account)
                <tr>
                    <td>@can('cash_accounts.update')<button type="button" class="link-button v65-record-link" wire:click="editCash({{ $account->id }})">{{ $account->code }}</button>@else{{ $account->code }}@endcan</td>
                    <td>@can('cash_accounts.update')<button type="button" class="link-button v65-record-link" wire:click="editCash({{ $account->id }})">{{ $account->name }}</button>@else{{ $account->name }}@endcan</td>
                    <td>{{ $account->currency }}</td>
                    <td>{{ $account->balance() }}</td>
                    <td>{{ $account->is_active ? 'Aktif' : 'Pasif' }}</td>
                    <td class="v65-row-actions">
                        @can('cash_accounts.update')
                            <button @class(['button-warning' => $account->is_active, 'button-success' => ! $account->is_active]) type="button" wire:click="toggleCash({{ $account->id }})">
                                {{ $account->is_active ? 'Pasife Al' : 'Aktifleştir' }}
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="panel stack v65-workspace-form">
        <h2>Banka</h2>
        @can('bank_accounts.create')
            <div class="v65-inline-action-form">
                <input wire:model="bankCode" placeholder="Kod">
                <input wire:model="bankName" placeholder="Banka">
                <input wire:model="bankAccountName" placeholder="Hesap adı">
                <input wire:model="bankIban" placeholder="IBAN">
                <input wire:model="bankCurrency" maxlength="3" placeholder="TRY">
                <button type="button" wire:click="saveBank">{{ $bankEditId ? 'Bankayı Güncelle' : 'Banka Ekle' }}</button>
                @if($bankEditId)
                    <button class="button-secondary" type="button" wire:click="cancelBankEdit">Vazgeç</button>
                @endif
            </div>
        @endcan

        <div class="table-scroll v65-list-table"><table class="data-table">
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
                    <td>@can('bank_accounts.update')<button type="button" class="link-button v65-record-link" wire:click="editBank({{ $account->id }})">{{ $account->code }}</button>@else{{ $account->code }}@endcan</td>
                    <td>@can('bank_accounts.update')<button type="button" class="link-button v65-record-link" wire:click="editBank({{ $account->id }})">{{ $account->bank_name }}</button>@else{{ $account->bank_name }}@endcan</td>
                    <td>@can('bank_accounts.update')<button type="button" class="link-button v65-record-link" wire:click="editBank({{ $account->id }})">{{ $account->account_name }}</button>@else{{ $account->account_name }}@endcan</td>
                    <td>{{ $account->iban }}</td>
                    <td>{{ $account->currency }}</td>
                    <td>{{ $book }}</td>
                    <td>{{ $statement ?? '—' }}</td>
                    <td>{{ $difference ?? '—' }}</td>
                    <td>{{ $account->unreconciledStatementCount() }}</td>
                    <td>{{ $account->is_active ? 'Aktif' : 'Pasif' }}</td>
                    <td class="v65-row-actions">
                        @can('bank_accounts.update')
                            <button @class(['button-warning' => $account->is_active, 'button-success' => ! $account->is_active]) type="button" wire:click="toggleBank({{ $account->id }})">
                                {{ $account->is_active ? 'Pasife Al' : 'Aktifleştir' }}
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
</div>
