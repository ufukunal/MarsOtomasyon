<?php

namespace App\Modules\Core\Enums;

enum DocumentType: string
{
    case Quote = 'quote';
    case SalesOrder = 'sales_order';
    case Dispatch = 'dispatch';
    case SalesInvoice = 'sales_invoice';
    case PurchaseOrder = 'purchase_order';
    case GoodsReceipt = 'goods_receipt';
    case SupplierInvoice = 'supplier_invoice';
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
            self::PurchaseOrder => 'Satınalma Siparişi',
            self::GoodsReceipt => 'Mal Kabul',
            self::SupplierInvoice => 'Alış Faturası',
            self::Collection => 'Tahsilat',
            self::Payment => 'Ödeme',
            self::Expense => 'Gider',
        };
    }
}
