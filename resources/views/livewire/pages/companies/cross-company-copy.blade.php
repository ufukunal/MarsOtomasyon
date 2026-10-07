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
        <div class="form-row">
            @if($canCopy)
                <button type="button" wire:click="copy">Seçilenleri Kopyala</button>
            @endif
            @if($sourceCompanyId && $canViewSource)
                <button type="button" wire:click="inspectSourceChanges">Kaynakta Değişti mi?</button>
            @endif
        </div>
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
            @if($canCopy)
                <button type="button" wire:click="copy">Kararlarla Devam Et</button>
            @endif
        </section>
    @endif

    @if($sourceChanges !== [])
        <section class="panel stack">
            <h2>Kaynak Değişiklikleri</h2>
            @foreach($sourceChanges as $row)
                <article class="source-change">
                    <div class="form-row">
                        <strong>{{ $row['code'] }} · {{ $row['label'] }}</strong>
                        @if($row['source_missing'])
                            <span class="field-error">Kaynak kayıt artık bulunamıyor.</span>
                        @elseif($canRefresh)
                            <button type="button" wire:click="refreshFromSource({{ $row['target_id'] }})">Kaynakla Güncelle</button>
                        @endif
                    </div>
                    @if(!$row['source_missing'])
                        <table class="data-table">
                            <thead><tr><th>Alan</th><th>Hedef</th><th>Kaynak</th></tr></thead>
                            <tbody>
                            @foreach($row['changes'] as $change)
                                <tr><td>{{ $change['field'] }}</td><td>{{ $change['target'] }}</td><td>{{ $change['source'] }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </article>
            @endforeach
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
