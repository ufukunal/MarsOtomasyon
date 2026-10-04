<div class="stack">
    <section class="panel stack">
        <div class="form-grid">
            <label class="field"><span class="field-label">Lokasyon</span>
                <select wire:model="locationId" @disabled($slip && $slip->status !== 'draft')>
                    <option value="">Seçin</option>
                    @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>@endforeach
                </select>
            </label>
            <label class="field"><span class="field-label">Tarih</span>
                <input type="date" wire:model="slipDate" @disabled($slip && $slip->status !== 'draft')>
            </label>
            <label class="field"><span class="field-label">Yön</span>
                <select wire:model.live="direction" @disabled($slip && $slip->status !== 'draft')>
                    <option value="in">Giriş</option><option value="out">Çıkış</option>
                </select>
            </label>
            <label class="field"><span class="field-label">Sebep</span>
                <select wire:model="reason" @disabled($slip && $slip->status !== 'draft')>
                    @foreach($reasonOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="field"><span class="field-label">Not</span>
                <textarea wire:model="note" @disabled($slip && $slip->status !== 'draft')></textarea>
            </label>
        </div>

        @if(!$slip || $slip->status === 'draft')
            <table class="data-table"><thead><tr><th>Ürün</th><th>Miktar</th>@if($direction === 'in' && $canViewCost)<th>Birim Maliyet</th>@endif<th>Not</th><th></th></tr></thead><tbody>
            @foreach($draftLines as $index => $line)
                <tr wire:key="warehouse-slip-line-{{ $index }}">
                    <td><select wire:model="draftLines.{{ $index }}.product_id"><option value="">Ürün</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>@endforeach</select></td>
                    <td><input data-tr-decimal wire:model="draftLines.{{ $index }}.quantity" inputmode="decimal"></td>
                    @if($direction === 'in' && $canViewCost)<td><input data-tr-decimal wire:model="draftLines.{{ $index }}.unit_cost" inputmode="decimal" placeholder="Boş = mevcut ortalama"></td>@endif
                    <td><input wire:model="draftLines.{{ $index }}.note"></td>
                    <td><button type="button" wire:click="removeLine({{ $index }})">Kaldır</button></td>
                </tr>
            @endforeach
            </tbody></table>
            <div class="form-row">
                <button type="button" wire:click="addLine">Satır Ekle</button>
                <button type="button" wire:click="saveDraft">Taslağı Kaydet</button>
                @if($slip && auth()->user()?->can('warehouse_slips.update'))<button type="button" wire:click="post">{{ $acceptCostDeviation ? 'Uyarıya Rağmen Kesinleştir' : 'Kesinleştir' }}</button>@endif
            </div>
        @else
            <div class="form-row"><strong>{{ $slip->number }}</strong><span>Durum: {{ $slip->status }}</span></div>
            <table class="data-table"><thead><tr><th>Ürün</th><th>Miktar</th>@if($canViewCost)<th>Birim Maliyet</th>@endif<th>Not</th></tr></thead><tbody>
            @foreach($postedLines as $line)<tr><td>{{ $line->product->code }} · {{ $line->product->name }}</td><td>{{ \App\Support\Formatting\TrFormatter::quantity((string)$line->quantity) }}</td>@if($canViewCost)<td>{{ $line->unit_cost !== null ? \App\Support\Formatting\TrFormatter::money((string)$line->unit_cost,4) : '—' }}</td>@endif<td>{{ $line->note }}</td></tr>@endforeach
            </tbody></table>
            @if($slip->status === 'posted' && auth()->user()?->can('warehouse_slips.cancel'))<button type="button" wire:click="reverse">Ters Fişle İptal Et</button>@endif
        @endif
    </section>

    @foreach($costWarnings as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach
    @foreach($errors->all() as $error)<div class="field-error">{{ $error }}</div>@endforeach
</div>
