<div class="space-y-5 panel v65-list-page">
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
    @if($currencies !== [])
        <label>Para birimi
            <select wire:model.live="currency">
                @foreach($currencies as $code)
                    <option value="{{ $code }}">{{ $code }}</option>
                @endforeach
            </select>
        </label>
    @endif

    @if($aging)
        <div>Bakiye: {{ $aging->balance }} {{ $aging->currency }} · Açık borç: {{ $aging->openDebit }} {{ $aging->currency }} · Fazla alacak: {{ $aging->excessCredit }} {{ $aging->currency }}</div>
        <div class="table-scroll v65-list-table"><table class="data-table">
            <thead><tr><th>Tarih</th><th>Vade</th><th>Orijinal</th><th>Mahsup edilen alacak</th><th>Kalan</th><th>Dilim</th><th>Durum</th></tr></thead>
            <tbody>
                @foreach($aging->lines as $line)
                    <tr>
                        <td>{{ $line->transactionDate }}</td>
                        <td>{{ $line->dueDate }}</td>
                        <td>{{ $line->originalAmount }}</td>
                        <td>{{ $line->appliedCredit }}</td>
                        <td>{{ $line->remaining }}</td>
                        <td>{{ $line->bucket }}</td>
                        <td>{{ match($line->color) { 'green' => 'Kapandı', 'yellow' => 'Kısmen kapandı', default => 'Açık' } }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
</div>
