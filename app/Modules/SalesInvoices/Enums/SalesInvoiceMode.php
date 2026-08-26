<?php

namespace App\Modules\SalesInvoices\Enums;

enum SalesInvoiceMode: string
{
    case Direct = 'direct';
    case OrderLinked = 'order_linked';
    case DispatchLinked = 'dispatch_linked';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Doğrudan Fatura',
            self::OrderLinked => 'Sipariş Bağlı',
            self::DispatchLinked => 'İrsaliye Bağlı',
        };
    }
}
