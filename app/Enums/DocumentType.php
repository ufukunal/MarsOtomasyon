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
    case PurchaseOrder = 'purchase_order';
    case GoodsReceipt = 'goods_receipt';
    case SupplierInvoice = 'supplier_invoice';
    case Payment = 'payment';
    case Expense = 'expense';
    case Advance = 'advance';
    case AdvanceReturn = 'advance_return';

    public function isLineCalculated(): bool
    {
        return in_array($this, [
            self::Quote,
            self::SalesOrder,
            self::Dispatch,
            self::SalesInvoice,
            self::Proforma,
            self::PurchaseOrder,
            self::GoodsReceipt,
            self::SupplierInvoice,
            self::Expense,
        ], true);
    }

    public function isHeaderAmount(): bool
    {
        return in_array($this, [
            self::Collection,
            self::ContactDebitCredit,
            self::Payment,
            self::Advance,
            self::AdvanceReturn,
        ], true);
    }
}
