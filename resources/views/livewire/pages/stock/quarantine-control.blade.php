<div class="stack">
    <section class="panel stack">
        <div class="form-row">
            <label class="field">
                <span class="field-label">Durum</span>
                <select wire:model.live="status">
                    <option value="open">Bekleyen / Kısmi</option>
                    <option value="pending">Bekleyen</option>
                    <option value="partial">Kısmi Kararlı</option>
                    <option value="released">Satılabilir</option>
                    <option value="scrapped">Hurda</option>
                    <option value="all">Tümü</option>
                </select>
            </label>
            <label class="field">
                <span class="field-label">Karar Notu</span>
                <input wire:model="decisionNote">
            </label>
        </div>

        <table class="data-table">
            <thead>
            <tr>
                <th></th>
                <th>Ürün</th>
                <th>Lokasyon</th>
                <th>Miktar</th>
                <th>Bekleyen</th>
                <th>Karar Miktarı</th>
                <th>Kaynak</th>
                <th>Bekleme</th>
                <th>Durum</th>
            </tr>
            </thead>
            <tbody>
            @foreach($entries as $entry)
                @php($days = $entry->created_at?->diffInDays(now()) ?? 0)
                <tr @class(['row-warning' => $days > 30 && in_array($entry->status, ['pending','partial'], true)])>
                    <td>
                        @if(in_array($entry->status, ['pending','partial'], true))
                            <input type="checkbox" wire:model="selected.{{ $entry->id }}">
                        @endif
                    </td>
                    <td>{{ $entry->product->code }} · {{ $entry->product->name }}</td>
                    <td>{{ $entry->location->name }}</td>
                    <td>{{ AppSupportFormattingTrFormatter::quantity((string) $entry->quantity) }}</td>
                    <td>{{ AppSupportFormattingTrFormatter::quantity($entry->pendingQuantity()) }}</td>
                    <td>
                        @if(in_array($entry->status, ['pending','partial'], true))
                            <input data-tr-decimal wire:model="decisionQuantities.{{ $entry->id }}" inputmode="decimal">
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        {{ $entry->source_document_type ?: 'Manuel' }}
                        @if($entry->source_document_id) #{{ $entry->source_document_id }} @endif
                    </td>
                    <td>{{ $days }} gün</td>
                    <td>{{ $entry->status }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{ $entries->links() }}

        @if(auth()->user()?->can('quarantine.update'))
            <div class="form-row">
                <button type="button" wire:click="releaseSelected">Satılabilir</button>
                <button type="button" wire:click="scrapSelected">Hurda</button>
            </div>
        @endif
    </section>
</div>
