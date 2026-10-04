<div class="space-y-5">
    <h1>Cari Yaşlandırma</h1>

    <label>Cari
        <select wire:model.live="contactId">
            <option value="">Seçin</option>
            @foreach($contacts as $contact)
                <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
            @endforeach
        </select>
    </label>
    <label>Rapor tarihi <input type="date" wire:model.live="asOf"></label>

    @if($aging)
        <div>Bakiye: {{ $aging->balance }} · Açık borç: {{ $aging->openDebit }} · Fazla kredi: {{ $aging->excessCredit }}</div>
        <table>
            <thead><tr><th>Tarih</th><th>Vade</th><th>Orijinal</th><th>Uygulanan kredi</th><th>Kalan</th><th>Dilim</th><th>Durum</th></tr></thead>
            <tbody>
                @foreach($aging->lines as $line)
                    <tr>
                        <td>{{ $line->transactionDate }}</td>
                        <td>{{ $line->dueDate }}</td>
                        <td>{{ $line->originalAmount }}</td>
                        <td>{{ $line->appliedCredit }}</td>
                        <td>{{ $line->remaining }}</td>
                        <td>{{ $line->bucket }}</td>
                        <td>{{ $line->color }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
