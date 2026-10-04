<div class="space-y-5">
    <h1>Araç Sıcak Satış</h1>

    <label>Araç
        <select wire:model="vehicleLocationId">
            <option value="">Seçin</option>
            @foreach($vehicles as $vehicle)
                <option value="{{ $vehicle->id }}">{{ $vehicle->code }} · {{ $vehicle->name }}</option>
            @endforeach
        </select>
    </label>

    <label>Cari
        <select wire:model="contactId">
            <option value="">Seçin</option>
            @foreach($contacts as $contact)
                <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
            @endforeach
        </select>
    </label>

    <label>Tarih <input type="date" wire:model="documentDate"></label>

    <table>
        <thead><tr><th>Ürün</th><th>Birim</th><th>Miktar</th><th>Fiyat</th><th>KDV</th><th></th></tr></thead>
        <tbody>
            @foreach($lines as $index => $line)
                <tr wire:key="hot-{{ $index }}">
                    <td>
                        <select wire:model="lines.{{ $index }}.product_id">
                            <option value="">Seçin</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select wire:model="lines.{{ $index }}.unit_id">
                            <option value="">Seçin</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input wire:model="lines.{{ $index }}.quantity"></td>
                    <td><input wire:model="lines.{{ $index }}.unit_price"></td>
                    <td><input wire:model="lines.{{ $index }}.vat_rate"></td>
                    <td><button type="button" wire:click="removeLine({{ $index }})">Sil</button></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <button type="button" wire:click="addLine">Satır Ekle</button>
    <button type="button" wire:click="post">Satışı Kesinleştir</button>
</div>
