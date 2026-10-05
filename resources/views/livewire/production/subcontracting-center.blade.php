<div class="space-y-6">
    <section class="panel">
        <h1>Fason Üretim</h1>
        <table class="data-table">
            <thead><tr><th>Emir</th><th>Mamul</th><th>Fasoncu</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            @foreach($orders as $row)
                <tr>
                    <td>{{ $row->number ?? 'Taslak #'.$row->id }}</td>
                    <td>{{ $row->product->code }} · {{ $row->product->name }}</td>
                    <td>{{ $row->subcontractor?->title }}</td>
                    <td>{{ $row->status }}</td>
                    <td><button type="button" wire:click="selectOrder({{ $row->id }})">Aç</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    @if($order)
        <section class="panel space-y-3">
            <h2>{{ $order->number ?? 'Taslak #'.$order->id }} · {{ $order->subcontractor?->title }}</h2>
            <div>Fason lokasyon: {{ $order->subcontractorLocation?->code }} · {{ $order->subcontractorLocation?->name }}</div>

            @if(in_array($order->status, ['confirmed','in_progress'], true))
                @can('subcontracting.update')
                    <h3>Malzeme Gönder</h3>
                    <label>Kaynak Lokasyon
                        <select wire:model="sourceLocationId">
                            <option value="">Seçin</option>
                            @foreach($normalLocations as $location)
                                <option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Tarih <input type="date" wire:model="transferDate"></label>
                    @foreach($order->components as $component)
                        <label>
                            {{ $component->componentProduct->code }} · {{ $component->componentProduct->name }}
                            <input data-tr-decimal wire:model="transferQuantities.{{ $component->component_product_id }}">
                        </label>
                    @endforeach
                    <button type="button" wire:click="sendMaterials">Fasona Transfer Et</button>
                @endcan
            @endif

            <h3>Fason Lokasyon Stoku</h3>
            <table class="data-table">
                <thead><tr><th>Ürün</th><th>Miktar</th></tr></thead>
                <tbody>
                @forelse($balances as $balance)
                    <tr>
                        <td>{{ $balance->product->code }} · {{ $balance->product->name }}</td>
                        <td>{{ $balance->quantity }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">Fason lokasyonda stok yok.</td></tr>
                @endforelse
                </tbody>
            </table>

            <h3>Fason Transferleri</h3>
            <table class="data-table">
                <thead><tr><th>No</th><th>Durum</th><th>Satırlar</th></tr></thead>
                <tbody>
                @foreach($transfers as $transfer)
                    <tr>
                        <td>{{ $transfer->number }}</td>
                        <td>{{ $transfer->status }}</td>
                        <td>
                            @foreach($transfer->lines as $line)
                                <div>{{ $line->product->code }} · {{ $line->quantity }}</div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @can('subcontracting.update')
                <h3>Hizmet Faturası Bağla</h3>
                <select wire:model="serviceInvoiceId">
                    <option value="">Seçin</option>
                    @foreach($serviceInvoices as $invoice)
                        <option value="{{ $invoice->id }}">
                            {{ $invoice->number }} · {{ $invoice->document_date?->format('d.m.Y') }} · {{ $invoice->grand_total }} {{ $invoice->currency }}
                        </option>
                    @endforeach
                </select>
                <button type="button" wire:click="linkInvoice">Üretim Emrine Bağla</button>
            @endcan

            <h3>Bağlı Hizmet Faturaları</h3>
            @foreach($order->serviceInvoices as $mapping)
                <div>{{ $mapping->invoice->number }} · {{ $mapping->invoice->grand_total }} {{ $mapping->invoice->currency }}</div>
            @endforeach

            @can('cost.view')
                <h3>Hizmet Maliyet Dağılımı</h3>
                <table class="data-table">
                    <thead><tr><th>Fatura Satırı</th><th>Completion</th><th>Basis</th><th>Allocation</th><th>Applied</th></tr></thead>
                    <tbody>
                    @foreach($allocations as $allocation)
                        <tr>
                            <td>#{{ $allocation->purchase_invoice_line_id }}</td>
                            <td>#{{ $allocation->production_completion_id }}</td>
                            <td>{{ $allocation->quantity_basis }}</td>
                            <td>{{ $allocation->allocated_amount_base }}</td>
                            <td>{{ $allocation->applied_amount_base }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endcan
        </section>
    @endif
</div>
