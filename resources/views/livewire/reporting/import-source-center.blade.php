<div class="v65-list-page stack">
    <section class="panel stack">
        <div class="report-heading">
            <div>
                <div class="report-category">Raporlama / İthalat</div>
                <h2>Kaynak Eşleştirme</h2>
            </div>
            <a href="{{ route('reports.center', ['report' => 'imports.conversion']) }}" class="button-secondary">Dönüşüm Raporu</a>
        </div>

        <div class="alert alert-warning" role="note">
            İthalat ve konteyner kaynakları eski satış hareketlerinde otomatik olarak kayıtlı değildir.
            Yalnız belge, depo ve fiziksel kaynak teyit edildikten sonra eşleştirme yapın.
            Buradaki işlem stok, muhasebe veya cari bakiyeyi değiştirmez; rapor kaynağı oluşturur.
            Bir satış satırını birden fazla konteynere parçalı dağıtabilirsiniz.
        </div>

        @if($message)
            <div class="alert">{{ $message }}</div>
        @endif

        @can('import_shipments.update')
            <form wire:submit="save" class="stack" autocomplete="off">
                <div class="report-filters">
                    <label>
                        Hareket türü
                        <select wire:model.live="sourceType">
                            <option value="sales">Satış faturası</option>
                            <option value="return">Satış iadesi</option>
                            <option value="fire">Üretim firesi</option>
                            <option value="scrap">Hurda çıkışı</option>
                        </select>
                    </label>
                    <label>
                        Kaynak satır / hareket ID
                        <input type="number" min="1" step="1" wire:model="sourceId" required>
                        @error('sourceId') <span class="text-danger">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        İthalat / konteyner ara
                        <input type="search" wire:model.live.debounce.350ms="importSearch" placeholder="Dosya veya konteyner numarası">
                    </label>
                    <label>
                        İthalat / konteyner
                        <select wire:model="containerId" required>
                            <option value="">Konteyner seçin</option>
                            @foreach($containers as $container)
                                <option value="{{ $container->id }}">{{ $container->import_number }} / {{ $container->container_no }} ({{ $container->id }})</option>
                            @endforeach
                        </select>
                        @error('containerId') <span class="text-danger">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Eşleştirilecek temel miktar
                        <input type="text" inputmode="decimal" placeholder="1.000" wire:model="quantity" required>
                        @error('quantity') <span class="text-danger">{{ $message }}</span> @enderror
                    </label>
                </div>
                <label>
                    Eşleştirme kanıtı / gerekçesi
                    <input type="text" wire:model="note" maxlength="2000" required
                        placeholder="Ör. depo teslim listesi, konteyner ve fatura kontrolü">
                    @error('note') <span class="text-danger">{{ $message }}</span> @enderror
                </label>
                <div class="report-toolbar">
                    <button type="submit" wire:loading.attr="disabled" wire:target="save">Kaynak Eşleştirmesini Kaydet</button>
                </div>
            </form>
        @endcan
    </section>

    <section class="panel stack">
        <h2>Kaynak Hareketleri — Son 50 Kayıt</h2>
        <p class="text-muted">Miktarlar stok temel birimindedir. Tablodaki ID'yi eşleştirme formuna girin. Liste dışındaki eski kayıtların ID'si de kullanılabilir.</p>
        <div class="table-scroll v65-list-table">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Tarih</th><th>Belge</th><th>Ürün</th><th class="align-end">Temel Miktar</th><th>Seç</th></tr></thead>
                <tbody>
                    @forelse($sources as $source)
                        <tr>
                            <td>{{ $source->id }}</td>
                            <td>{{ $source->document_date }}</td>
                            <td>{{ $source->number ?? '—' }}</td>
                            <td>{{ $source->product_code }}</td>
                            <td class="align-end tabular">{{ $source->base_quantity ?? $source->quantity }}</td>
                            <td><button type="button" class="button-secondary" wire:click="$set('sourceId', '{{ $source->id }}')">Seç</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">Seçili türde işlem bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel stack">
        <h2>Aktif Kaynak Eşleştirmeleri</h2>
        @can('import_shipments.update')
            <label>
                İptal gerekçesi
                <input type="text" wire:model="voidReason" maxlength="2000" placeholder="İptal edilecek kaydın gerekçesi">
                @error('voidReason') <span class="text-danger">{{ $message }}</span> @enderror
            </label>
        @endcan
        <div class="table-scroll v65-list-table">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Kaynak</th><th>İthalat</th><th>Konteyner</th><th>Ürün</th><th class="align-end">Miktar</th><th>Açıklama</th><th>İşlem</th></tr></thead>
                <tbody>
                    @forelse($allocations as $allocation)
                        <tr wire:key="import-source-{{ $allocation->id }}">
                            <td>{{ $allocation->id }}</td>
                            <td>{{ $allocation->source_type }} #{{ $allocation->source_id }}</td>
                            <td>{{ $allocation->import_number }}</td>
                            <td>{{ $allocation->container_no }}</td>
                            <td>{{ $allocation->product_code }}</td>
                            <td class="align-end tabular">{{ $allocation->quantity }}</td>
                            <td>{{ $allocation->note }}</td>
                            <td>
                                @can('import_shipments.update')
                                    <button type="button" class="button-danger"
                                        wire:click="voidAllocation({{ $allocation->id }})"
                                        wire:confirm="Seçili eşleştirme gerekçesiyle iptal edilsin mi?">
                                        İptal Et
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Henüz doğrulanmış kaynak eşleştirmesi bulunmuyor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
