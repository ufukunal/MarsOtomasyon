@props(['document'])
@php
    // A source record without view permission must not disclose its number.
    $visibleSources = $document->incomingRelations->map(function ($relation) {
        $source = $relation->sourceDocument;
        $routeAndPermission = match ($source?->document_type) {
            \App\Enums\DocumentType::Quote => ['sales.quotes.edit', 'quotes.view'],
            \App\Enums\DocumentType::SalesOrder => ['sales.orders.edit', 'sales_orders.view'],
            \App\Enums\DocumentType::Dispatch => ['sales.dispatches.edit', 'dispatches.view'],
            \App\Enums\DocumentType::SalesInvoice => ['sales.invoices.edit', 'sales_invoices.view'],
            \App\Enums\DocumentType::PurchaseQuote => ['purchases.quotes.edit', 'purchase_quotes.view'],
            \App\Enums\DocumentType::PurchaseOrder => ['purchases.orders.edit', 'purchase_orders.view'],
            \App\Enums\DocumentType::PurchaseDispatch => ['purchases.dispatches.edit', 'purchase_dispatches.view'],
            \App\Enums\DocumentType::GoodsReceipt => ['purchases.receipts.edit', 'goods_receipts.view'],
            \App\Enums\DocumentType::SupplierInvoice => ['purchases.invoices.edit', 'supplier_invoices.view'],
            default => null,
        };

        if (! $source || ! $routeAndPermission || ! auth()->user()?->can($routeAndPermission[1])) {
            return null;
        }

        return ['source' => $source, 'route' => $routeAndPermission[0]];
    })->filter()->values();
@endphp
@if($visibleSources->isNotEmpty())
    <div {{ $attributes->class(['v65-doc-source-references']) }}>
        <strong>Kaynak Belgeler</strong>
        @foreach($visibleSources as $sourceLink)
            <a class="v65-record-link"
                href="{{ route($sourceLink['route'], ['id' => $sourceLink['source']->id]) }}">{{ $sourceLink['source']->number ?? 'Taslak #'.$sourceLink['source']->id }}</a>
        @endforeach
    </div>
@endif
