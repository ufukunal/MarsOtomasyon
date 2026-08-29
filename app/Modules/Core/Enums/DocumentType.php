<?php

namespace App\Modules\Core\Enums;

enum DocumentType: string
{
    case Quote = 'quote';
    case SalesOrder = 'sales_order';
    case Dispatch = 'dispatch';
    case SalesInvoice = 'sales_invoice';
    case SalesReturn = 'sales_return';
    case PurchaseOrder = 'purchase_order';
    case GoodsReceipt = 'goods_receipt';
    case SupplierInvoice = 'supplier_invoice';
    case PurchaseReturn = 'purchase_return';
    case Collection = 'collection';
    case Payment = 'payment';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Teklif',
            self::SalesOrder => 'Satış Siparişi',
            self::Dispatch => 'İrsaliye / Sevkiyat',
            self::SalesInvoice => 'Satış Faturası',
            self::SalesReturn => 'Satış İadesi / RMA',
            self::PurchaseOrder => 'Satınalma Siparişi',
            self::GoodsReceipt => 'Mal Kabul',
            self::SupplierInvoice => 'Alış Faturası',
            self::PurchaseReturn => 'Satınalma İadesi',
            self::Collection => 'Tahsilat',
            self::Payment => 'Ödeme',
            self::Expense => 'Gider',
        };
    }
}
