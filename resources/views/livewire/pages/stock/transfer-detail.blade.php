<div class="stack">
    <section class="panel stack">
        <div class="form-grid">
            <label class="field">
                <span class="field-label">Kaynak</span>
                <select wire:model="fromLocationId" @disabled($transfer && $transfer->status !== 'draft')>
                    <option value="">Seçin</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field-label">Hedef</span>
                <select wire:model="toLocationId" @disabled($transfer && $transfer->status !== 'draft')>
                    <option value="">Seçin</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field-label">Tarih</span>
                <input type="date" wire:model="transferDate" @disabled($transfer && $transfer->status !== 'draft')>
            </label>
            <label class="field">
                <span class="field-label">Not</span>
                <textarea wire:model="note" @disabled($transfer && $transfer->status !== 'draft')></textarea>
            </label>
        </div>

        @if(!$transfer || $transfer->status === 'draft')
            <table class="data-table">
                <thead><tr><th>Ürün</th><th>Miktar</th><th></th></tr></thead>
                <tbody>
                @foreach($draftLines as $index => $line)
                    <tr wire:key="transfer-draft-line-{{ $index }}">
                        <td>
                            <select wire:model="draftLines.{{ $index }}.product_id">
                                <option value="">Ürün seçin</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input data-tr-decimal wire:model="draftLines.{{ $index }}.quantity" inputmode="decimal"></td>
                        <td><button type="button" wire:click="removeLine({{ $index }})">Kaldır</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="form-row">
                <button type="button" wire:click="addLine">Satır Ekle</button>
                <button type="button" wire:click="saveDraft">Taslağı Kaydet</button>
                @if($transfer && auth()->user()?->can('transfers.update'))
                    <button type="button" wire:click="send">Gönder</button>
                @endif
                @if($transfer && auth()->user()?->can('transfers.cancel'))
                    <button type="button" wire:click="cancel">İptal Et</button>
                @endif
            </div>
        @else
            <div class="form-row">
                <strong>{{ $transfer->number }}</strong>
                <span>Durum: {{ $transfer->status }}</span>
            </div>

            <table class="data-table">
                <thead><tr><th>Ürün</th><th>Gönderilen</th><th>Teslim Alınan</th><th>Kalan</th><th>Şimdi Teslim</th></tr></thead>
                <tbody>
                @foreach($postedLines as $line)
                    <tr>
                        <td>{{ $line->product->code }} · {{ $line->product->name }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::quantity((string) $line->quantity) }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::quantity((string) $line->received_quantity) }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::quantity($line->remainingQuantity()) }}</td>
                        <td>
                            @if(in_array($transfer->status, ['in_transit','partially_received'], true))
                                <input data-tr-decimal wire:model="receiveQuantities.{{ $line->id }}" inputmode="decimal">
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="form-row">
                @if(in_array($transfer->status, ['in_transit','partially_received'], true) && auth()->user()?->can('transfers.update'))
                    <button type="button" wire:click="receive">Teslim Al</button>
                @endif
                @if(in_array($transfer->status, ['in_transit','partially_received'], true) && auth()->user()?->can('transfers.cancel'))
                    <button type="button" wire:click="cancel">Kalan Transferi İptal Et</button>
                @endif
            </div>
        @endif
    </section>

    @foreach($errors->all() as $error)
        <div class="field-error">{{ $error }}</div>
    @endforeach
</div>
