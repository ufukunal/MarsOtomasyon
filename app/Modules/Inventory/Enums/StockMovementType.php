<?php

namespace App\Modules\Inventory\Enums;

enum StockMovementType: string
{
    case OpeningIn = 'opening_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case DispatchOut = 'dispatch_out';
    case InvoiceOut = 'invoice_out';
    case GoodsReceiptIn = 'goods_receipt_in';
    case PurchaseReturnOut = 'purchase_return_out';
    case SalesReturnIn = 'sales_return_in';

    public function label(): string
    {
        return match ($this) {
            self::OpeningIn => 'Açılış Girişi',
            self::AdjustmentIn => 'Düzeltme Girişi',
            self::AdjustmentOut => 'Düzeltme Çıkışı',
            self::TransferIn => 'Transfer Girişi',
            self::TransferOut => 'Transfer Çıkışı',
            self::DispatchOut => 'İrsaliye Çıkışı',
            self::InvoiceOut => 'Fatura Çıkışı',
            self::GoodsReceiptIn => 'Mal Kabul Girişi',
            self::PurchaseReturnOut => 'Satınalma İade Çıkışı',
            self::SalesReturnIn => 'Satış İade Girişi',
        };
    }

    public function isInbound(): bool
    {
        return match ($this) {
            self::OpeningIn, self::AdjustmentIn, self::TransferIn, self::GoodsReceiptIn, self::SalesReturnIn => true,
            self::AdjustmentOut, self::TransferOut, self::DispatchOut, self::InvoiceOut, self::PurchaseReturnOut => false,
        };
    }
}
