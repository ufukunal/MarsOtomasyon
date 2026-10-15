@props(['document'])
@if($document->incomingRelations->isNotEmpty())
        <div {{ $attributes->class(['v65-doc-source-references']) }}>
            <strong>Kaynak Belgeler</strong>
            @foreach($document->incomingRelations as $relation)
                @php
                    $source = $relation->sourceDocument;
                    $routeAndPermission = match ($source?->document_type) {
                        \App\Enums\DocumentType::Quote => ['sales.quotes.edit', 'quotes.view'],
                        \App\Enums\DocumentType::SalesOrder => ['sales.orders.edit', 'sales_orders.view'],
                        \App\Enums\DocumentType::Dispatch => ['sales.dispatches.edit', 'dispatches.view'],
                        \App\Enums\DocumentType::SalesInvoice => ['sales.invoices.edit', 'sales_invoices.view'],
                        \App\Enums\DocumentType::PurchaseOrder => ['purchases.orders.edit', 'purchase_orders.view'],
                        \App\Enums\DocumentType::GoodsReceipt => ['purchases.receipts.edit', 'goods_receipts.view'],
                        \App\Enums\DocumentType::SupplierInvoice => ['purchases.invoices.edit', 'supplier_invoices.view'],
                        default => null,
                    };
                @endphp
                @if($source)
                    @if($routeAndPermission && auth()->user()?->can($routeAndPermission[1]))
                        <a class="v65-record-link" href="{{ route($routeAndPermission[0], ['id' => $source->id]) }}">{{ $source->number ?? 'Taslak #'.$source->id }}</a>
                    @else
                        <span>{{ $source->number ?? 'Taslak #'.$source->id }}</span>
                    @endif
                @endif
            @endforeach
        </div>
    @endif
