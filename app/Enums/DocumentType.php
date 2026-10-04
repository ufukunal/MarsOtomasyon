<?php

namespace App\Enums;

enum DocumentType: string
{
    case Quote = 'quote';
    case SalesOrder = 'sales_order';
    case Dispatch = 'dispatch';
    case SalesInvoice = 'sales_invoice';
    case Proforma = 'proforma';
    case Collection = 'collection';
    case ContactDebitCredit = 'contact_debit_credit';

    public function isLineCalculated(): bool
    {
        return in_array($this, [
            self::Quote,
            self::SalesOrder,
            self::Dispatch,
            self::SalesInvoice,
            self::Proforma,
        ], true);
    }

    public function isHeaderAmount(): bool
    {
        return in_array($this, [self::Collection, self::ContactDebitCredit], true);
    }
}
