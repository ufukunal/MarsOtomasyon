<div class="stack">
    <section class="panel form-row">
        <select wire:model.live="type" wire:change="loadSource">
            <option value="contact">Cari</option>
            <option value="product">Ürün</option>
        </select>
        <select wire:model.live="sourceCompanyId" wire:change="loadSource">
            <option value="">Kaynak şirket seçin</option>
            @foreach($sourceCompanies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach
        </select>
    </section>

    <section class="panel">
        <table class="data-table">
            <thead><tr><th></th><th>Kod</th><th>Kayıt</th></tr></thead>
            <tbody>
            @foreach($sourceRows as $row)
                <tr><td><input type="checkbox" wire:model="selected" value="{{ $row['id'] }}"></td><td>{{ $row['code'] }}</td><td>{{ $row['label'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <button type="button" wire:click="copy">Seçilenleri Kopyala</button>
    </section>

    @if($conflicts)
        <section class="panel stack">
            <h2>Kod Çakışmaları</h2>
            @foreach($conflicts as $conflict)
                <div class="conflict-row">
                    <strong>{{ $conflict['source_code'] }} · {{ $conflict['source_label'] }}</strong>
                    <span>Hedefte: {{ $conflict['existing_target_label'] }}</span>
                    <select wire:model="choices.{{ $conflict['source_id'] }}.action">
                        <option value="">Karar verin</option>
                        <option value="existing">Mevcut kartı kullan</option>
                        <option value="new_code">Yeni kod ver</option>
                        <option value="cancel">İptal</option>
                    </select>
                    @if(($choices[$conflict['source_id']]['action'] ?? null) === 'new_code')
                        <input wire:model="choices.{{ $conflict['source_id'] }}.new_code" placeholder="Yeni kod">
                    @endif
                </div>
            @endforeach
            <button type="button" wire:click="copy">Kararlarla Devam Et</button>
        </section>
    @endif

    @foreach($warnings as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach

    @if($result)
        <section class="panel">
            <strong>Kopyalanan: {{ count($result['copied'] ?? []) }}</strong>
            <span> · Mevcut kullanılan: {{ count($result['existing'] ?? []) }}</span>
            <span> · İptal: {{ count($result['cancelled'] ?? []) }}</span>
        </section>
    @endif
</div>
