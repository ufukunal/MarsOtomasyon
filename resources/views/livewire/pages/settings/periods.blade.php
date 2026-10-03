<div class="stack">
    @can('periods.create')
        <section class="panel">
            <h2>Yeni Dönem Aç</h2>
            <div class="form-row">
                <select wire:model="companyId">
                    <option value="">Şirket seçin</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
                <input wire:model="year" type="number" min="2000" max="2200">
                <button wire:click="createPeriod" type="button">Yeni Dönem Aç</button>
            </div>
            @error('companyId') <div class="field-error">{{ $message }}</div> @enderror
            @error('year') <div class="field-error">{{ $message }}</div> @enderror
        </section>
    @endcan

    <section class="panel">
        <table class="data-table">
            <thead>
            <tr>
                <th>Şirket</th>
                <th>Yıl</th>
                <th>Veritabanı</th>
                <th>Durum</th>
                <th>Devir</th>
                <th>Eylemler</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($periods as $period)
                <tr>
                    <td>{{ $period->company->name }}</td>
                    <td>{{ $period->year }}</td>
                    <td><code>{{ $period->database_name }}</code></td>
                    <td>{{ $period->status }}</td>
                    <td>{{ $period->carried_at ? 'Yapıldı' : 'Yapılmadı' }}</td>
                    <td>
                        @if ($period->status === 'active')
                            @can('periods.cancel')
                                <button type="button" wire:click="close({{ $period->id }})">Dönemi Kapat</button>
                            @endcan
                        @elseif ($period->status === 'closed')
                            @can('periods.reopen')
                                <input wire:model="reopenReason" type="text" placeholder="Yeniden açma gerekçesi">
                                <button type="button" wire:click="reopen({{ $period->id }})">Yeniden Aç</button>
                            @endcan
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>
