<div class="stack v65-catalog-form">
    <nav class="v65-product-edit-navigation" aria-label="Birim gezinme">
        <a href="{{ route('units.index') }}">← Birim Listesi</a>
    </nav>
    <section class="panel form-grid v65-product-edit-card" aria-label="{{ $unit ? 'Birim düzenleme' : 'Yeni birim' }}">
        <x-field.text label="Kod" wire:model="code" :disabled="(bool) $unit" />
        <x-field.text label="Ad" wire:model="name" />
        <x-field.toggle label="Temel birim" wire:model="isBase" />
        <x-field.toggle label="Aktif" wire:model="isActive" />
    </section>
    @error('code')<div class="field-error" role="alert">{{ $message }}</div>@enderror
    @error('name')<div class="field-error" role="alert">{{ $message }}</div>@enderror
    @error('is_base')<div class="field-error" role="alert">{{ $message }}</div>@enderror
    <div class="v65-product-edit-savebar">
        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save">{{ $unit ? 'Değişiklikleri Kaydet' : 'Birimi Kaydet' }}</button>
        <a class="button-secondary" href="{{ route('units.index') }}">Listeye Dön</a>
    </div>
    @if($unit)
        <section class="panel stack">
            <h2>Birim Dönüşümleri</h2>
            <div class="form-row">
                <label class="field">
                    <span class="field-label">Hedef Birim</span>
                    <select wire:model="toUnitId">
                        <option value="">Birim seçin</option>
                        @foreach($units as $u)<option value="{{ $u->id }}">{{ $u->code }}</option>@endforeach
                    </select>
                </label>
                <label class="field">
                    <span class="field-label">Dönüşüm Katsayısı</span>
                    <input wire:model="factor" inputmode="decimal">
                </label>
                <button class="button-secondary" type="button" wire:click="addConversion" wire:loading.attr="disabled" wire:target="addConversion">Dönüşüm Ekle</button>
            </div>
            @error('toUnitId')<div class="field-error" role="alert">{{ $message }}</div>@enderror
            @error('factor')<div class="field-error" role="alert">{{ $message }}</div>@enderror
            <div class="table-scroll v65-list-table">
                <table class="data-table">
                    <thead><tr><th>Hedef</th><th>Katsayı</th><th>Ters</th></tr></thead>
                    <tbody>
                        @forelse($conversions as $row)
                            <tr><td>{{ $row->toUnit->code }}</td><td>{{ $row->factor }}</td><td>{{ bcdiv('1', (string) $row->factor, 6) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">Henüz dönüşüm tanımlanmadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
