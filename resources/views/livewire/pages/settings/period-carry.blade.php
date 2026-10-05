<div class="stack">
    <section class="panel">
        <h2>Dönem Devri Ön Kontrolü</h2>
        <div class="form-row">
            <label>
                <span>Kaynak dönem</span>
                <select wire:model.live="sourcePeriodId">
                    <option value="">Dönem seçin</option>
                    @foreach($sourcePeriods as $period)
                        <option value="{{ $period->id }}">
                            {{ $period->company->name }} — {{ $period->year }} ({{ $period->status }})
                        </option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Hedef yıl</span>
                <input wire:model="targetYear" type="number" min="2000" max="2200">
            </label>
            <button type="button" wire:click="previewCarry">Ön Kontrolü Çalıştır</button>
        </div>
        @error('sourcePeriodId') <div class="field-error">{{ $message }}</div> @enderror
        @error('targetYear') <div class="field-error">{{ $message }}</div> @enderror
    </section>

    @if($preview)
        <section class="panel">
            <h2>Kontrol Listesi</h2>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Kontrol</th>
                        <th>Durum</th>
                        <th>Adet</th>
                        <th>Açıklama</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($preview['checks'] as $check)
                        <tr>
                            <td>{{ $check['key'] }}</td>
                            <td>{{ strtoupper($check['status']) }}</td>
                            <td>{{ $check['count'] ?? '—' }}</td>
                            <td>{{ $check['message'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2>Kapanış / Taşıma Özeti</h2>
            <div class="table-scroll">
                <table class="data-table">
                    <tbody>
                    @foreach($preview['summary'] as $key => $value)
                        <tr>
                            <th>{{ $key }}</th>
                            <td>{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2>K-256 Açık Siparişler</h2>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Tür</th>
                        <th>Kaynak</th>
                        <th>Cari</th>
                        <th>Satır</th>
                        <th>Kalan / Rezervasyon</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($preview['sales_orders'] as $order)
                        <tr>
                            <td>Satış</td>
                            <td>#{{ $order['id'] }} {{ $order['number'] }}</td>
                            <td>{{ $order['contact_id'] ?? '—' }}</td>
                            <td>{{ count($order['lines']) }}</td>
                            <td>
                                @foreach($order['lines'] as $line)
                                    <div>
                                        #{{ $line['line_id'] }}:
                                        {{ $line['remaining_quantity'] }}
                                        / rezervasyon {{ $line['active_reservation_quantity'] }}
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                    @endforelse
                    @forelse($preview['purchase_orders'] as $order)
                        <tr>
                            <td>Satınalma</td>
                            <td>#{{ $order['id'] }} {{ $order['number'] }}</td>
                            <td>{{ $order['contact_id'] ?? '—' }}</td>
                            <td>{{ count($order['lines']) }}</td>
                            <td>
                                @foreach($order['lines'] as $line)
                                    <div>#{{ $line['line_id'] }}: {{ $line['remaining_quantity'] }}</div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        @if(empty($preview['sales_orders']))
                            <tr><td colspan="5" class="empty-state">Taşınacak açık sipariş yok.</td></tr>
                        @endif
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2>K-259 Açık İthalat / Karantina</h2>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Tür</th>
                        <th>Kaynak</th>
                        <th>Durum</th>
                        <th>Detay</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($preview['import_files'] as $file)
                        <tr>
                            <td>İthalat</td>
                            <td>#{{ $file['id'] }} {{ $file['number'] }}</td>
                            <td>{{ $file['status'] }}</td>
                            <td>
                                {{ $file['containers'] }} konteyner,
                                {{ $file['packages'] }} paket,
                                {{ $file['cost_items'] }} maliyet kalemi
                            </td>
                        </tr>
                    @endforeach
                    @foreach($preview['quarantine'] as $entry)
                        <tr>
                            <td>Karantina</td>
                            <td>#{{ $entry['id'] }}</td>
                            <td>open</td>
                            <td>
                                Ürün {{ $entry['product_id'] }},
                                lokasyon {{ $entry['location_id'] }},
                                açık {{ $entry['open_quantity'] }}
                            </td>
                        </tr>
                    @endforeach
                    @if(empty($preview['import_files']) && empty($preview['quarantine']))
                        <tr><td colspan="4" class="empty-state">Taşınacak açık ithalat veya karantina kaydı yok.</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </section>

        @can('periods.update')
            <section class="panel">
                <button
                    type="button"
                    wire:click="carry"
                    @disabled(!($preview['can_carry'] ?? false))
                >
                    Dönem Devrini Başlat
                </button>
                @if(!($preview['can_carry'] ?? false))
                    <div class="field-error">BLOCK durumları çözülmeden carry başlatılamaz.</div>
                @endif
            </section>
        @endcan
    @endif

    @if($completedTargetPeriodId)
        <section class="panel">
            <h2>Dönem Erişimlerini Kopyala</h2>
            <p>
                Business carry tamamlandı. Yeni dönem erişimi otomatik verilmez.
                Yalnız seçtiğiniz kullanıcıların period access ve permission override kayıtları kopyalanır.
            </p>

            @forelse($accessCandidates as $candidate)
                <label class="form-row">
                    <input
                        type="checkbox"
                        wire:model="selectedAccessUserIds"
                        value="{{ $candidate->id }}"
                    >
                    <span>
                        {{ $candidate->name }} — {{ $candidate->email }}
                        @if($candidate->permission_overrides)
                            (override mevcut)
                        @endif
                    </span>
                </label>
            @empty
                <div class="empty-state">Kaynak dönemde aktif erişim adayı yok.</div>
            @endforelse

            @if($accessCandidates->isNotEmpty())
                <button type="button" wire:click="copyAccess">Seçili Erişimleri Kopyala</button>
            @endif
        </section>
    @endif
</div>
