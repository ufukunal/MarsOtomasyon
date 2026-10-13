<div class="stack v65-supplier-performance v65-list-page">
    <x-v65.list-heading title="Tedarikçi Performansı" />

    <label>Tedarikçi
        <select wire:model.live="contactId">
            <option value="">Seçin</option>
            @foreach($contacts as $contact)
                <option value="{{ $contact->id }}">{{ $contact->code }} · {{ $contact->title }}</option>
            @endforeach
        </select>
    </label>

    @if($metrics)
        <div class="panel"><div class="table-scroll v65-grid-scroll v65-list-table"><table class="data-table">
            <tbody>
            <tr><th>Satınalma Siparişi</th><td>{{ $metrics['purchase_orders'] }}</td></tr>
            <tr><th>Mal Kabul</th><td>{{ $metrics['goods_receipts'] }}</td></tr>
            <tr><th>Alış Faturası</th><td>{{ $metrics['supplier_invoices'] }}</td></tr>
            <tr><th>Zamanında Teslim %</th><td>{{ $metrics['on_time_receipt_rate'] }}</td></tr>
            <tr><th>Ort. Mutlak Fiyat Sapması %</th><td>{{ $metrics['average_price_variance_rate'] }}</td></tr>
            </tbody>
        </table></div></div>
    @endif
</div>
