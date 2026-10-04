<div class="space-y-8">
    <h1>Banka Ekstresi / Mutabakat</h1>

    <section class="space-y-3">
        <h2>Ekstre Dosyası</h2>
        <label>Banka
            <select wire:model="bankAccountId">
                <option value="">Seçin</option>
                @foreach($bankAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->bank_name }} · {{ $account->account_name }}</option>
                @endforeach
            </select>
        </label>
        <label>Format
            <select wire:model="format">
                <option value="auto">Otomatik</option>
                <option value="xlsx">Excel (.xlsx)</option>
                <option value="csv">CSV</option>
                <option value="mt940">MT940</option>
            </select>
        </label>
        <label>Dosya <input type="file" wire:model="file" accept=".xlsx,.csv,.txt,.mt940,.sta"></label>
        <button type="button" wire:click="preview">Önizle</button>
        <button type="button" wire:click="import">İçe Aktar</button>

        @if($importResult)
            <div>
                Aktarılan: {{ $importResult['imported'] }} · Tekrar: {{ $importResult['duplicates'] }} · Checksum: {{ $importResult['checksum'] }}
            </div>
        @endif
    </section>

    @if($previewRows !== [])
        <section>
            <h2>Önizleme</h2>
            <table>
                <thead><tr><th>Tarih</th><th>Valör</th><th>Referans</th><th>Açıklama</th><th>Yön</th><th>Tutar</th><th>Bakiye</th></tr></thead>
                <tbody>
                @foreach($previewRows as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['value_date'] ?? '—' }}</td>
                        <td>{{ $row['reference'] ?? '—' }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td>{{ $row['direction'] }}</td>
                        <td>{{ $row['amount'] }}</td>
                        <td>{{ $row['balance'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <section>
        <h2>Ekstre Satırları</h2>
        <table>
            <thead><tr><th>Seç</th><th>Banka</th><th>Tarih</th><th>Referans</th><th>Açıklama</th><th>Yön</th><th>Tutar</th><th>Durum</th></tr></thead>
            <tbody>
            @foreach($statementRows as $row)
                <tr>
                    <td><button type="button" wire:click="$set('selectedStatementId', {{ $row->id }})">Seç</button></td>
                    <td>{{ $row->account?->bank_name }}</td>
                    <td>{{ $row->movement_date?->format('d.m.Y') }}</td>
                    <td>{{ $row->reference }}</td>
                    <td>{{ $row->statement_description }}</td>
                    <td>{{ $row->direction }}</td>
                    <td>{{ $row->amount }}</td>
                    <td>{{ $row->reconciled_movement_id ? 'Mutabık' : 'Bekliyor' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    @if($selectedStatementId)
        <section class="space-y-3">
            <h2>Mutabakat · Ekstre #{{ $selectedStatementId }}</h2>

            @if($suggestions !== [])
                <h3>Öneriler</h3>
                <table>
                    <thead><tr><th>Hareket</th><th>Skor</th><th>Neden</th><th></th></tr></thead>
                    <tbody>
                    @foreach($suggestions as $suggestion)
                        <tr>
                            <td>#{{ $suggestion['movement_id'] }}</td>
                            <td>%{{ $suggestion['score'] }}</td>
                            <td>{{ $suggestion['reason'] }}</td>
                            <td><button type="button" wire:click="$set('selectedBookMovementId', {{ $suggestion['movement_id'] }})">Seç</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            <label>Mevcut Defter Hareketi
                <select wire:model="selectedBookMovementId">
                    <option value="">Seçin</option>
                    @foreach($bookRows as $row)
                        <option value="{{ $row->id }}">
                            #{{ $row->id }} · {{ $row->movement_date?->format('d.m.Y') }} · {{ $row->direction }} · {{ $row->amount }} · {{ $row->reference }}
                        </option>
                    @endforeach
                </select>
            </label>
            <button type="button" wire:click="reconcileExisting">Mevcut Hareketle Eşleştir</button>

            <hr>

            <label>Cari
                <select wire:model="selectedContactId">
                    <option value="">Cari etkisi yok</option>
                    @foreach($contacts as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
                    @endforeach
                </select>
            </label>
            <label>Yeni Hareket Türü <input wire:model="newMovementType"></label>
            <button type="button" wire:click="createFromStatement">Ekstreden Yeni Defter Hareketi</button>
        </section>
    @endif
</div>
