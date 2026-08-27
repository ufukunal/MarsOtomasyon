<?php

namespace App\Modules\Core\Enums;

enum AuditAction: string
{
    case CompanySettingsUpdated = 'core.company.settings.updated';
    case BranchCreated = 'core.branch.created';
    case BranchUpdated = 'core.branch.updated';
    case DocumentSequenceCreated = 'core.document_sequence.created';
    case DocumentSequenceUpdated = 'core.document_sequence.updated';
    case UserCreated = 'core.user.created';
    case UserUpdated = 'core.user.updated';
    case RoleCreated = 'core.role.created';
    case RoleUpdated = 'core.role.updated';
    case TaxCreated = 'core.tax.created';
    case TaxUpdated = 'core.tax.updated';
    case TaxZeroReasonCreated = 'core.tax_zero_reason.created';
    case TaxZeroReasonUpdated = 'core.tax_zero_reason.updated';
    case ExchangeRateCreated = 'core.exchange_rate.created';
    case ExchangeRateUpdated = 'core.exchange_rate.updated';
    case PostingPeriodCreated = 'core.posting_period.created';
    case PostingPeriodUpdated = 'core.posting_period.updated';
    case PostingPeriodClosed = 'core.posting_period.closed';
    case FileUploaded = 'core.file.uploaded';
    case AttachmentDetached = 'core.attachment.detached';
    case AccountCreated = 'accounts.account.created';
    case AccountUpdated = 'accounts.account.updated';
    case AccountProfileUpdated = 'accounts.account.profile.updated';
    case AccountRecordsUpdated = 'accounts.account.records.updated';
    case AccountB2BPolicyUpdated = 'accounts.account.b2b_policy.updated';
    case ProductCreated = 'products.product.created';
    case ProductUpdated = 'products.product.updated';
    case ProductSuppliersUpdated = 'products.product.suppliers.updated';
    case CategoryCreated = 'products.category.created';
    case CategoryUpdated = 'products.category.updated';
    case UnitCreated = 'products.unit.created';
    case UnitUpdated = 'products.unit.updated';
    case WarehouseCreated = 'inventory.warehouse.created';
    case WarehouseLocationCreated = 'inventory.warehouse_location.created';
    case StockMovementPosted = 'inventory.stock_movement.posted';
    case QuoteCreated = 'quotes.quote.created';
    case QuoteUpdated = 'quotes.quote.updated';
    case QuoteCancelled = 'quotes.quote.cancelled';
    case QuoteRevisionCreated = 'quotes.revision.created';
    case QuoteApproved = 'quotes.quote.approved';
    case QuoteRejected = 'quotes.quote.rejected';
    case QuoteConverted = 'quotes.quote.converted';
    case QuoteFinalizedPdfGenerated = 'quotes.finalized_pdf.generated';
    case SalesOrderCreated = 'sales_orders.order.created';
    case SalesOrderUpdated = 'sales_orders.order.updated';
    case GoodsReceiptQualityReclassified = 'goods_receipts.quality.reclassified';

    public function label(): string
    {
        return match ($this) {
            self::CompanySettingsUpdated => 'Firma / sistem ayarları güncellendi',
            self::BranchCreated => 'Şube oluşturuldu',
            self::BranchUpdated => 'Şube güncellendi',
            self::DocumentSequenceCreated => 'Numara serisi oluşturuldu',
            self::DocumentSequenceUpdated => 'Numara serisi güncellendi',
            self::UserCreated => 'Kullanıcı oluşturuldu',
            self::UserUpdated => 'Kullanıcı üyeliği güncellendi',
            self::RoleCreated => 'Rol oluşturuldu',
            self::RoleUpdated => 'Rol güncellendi',
            self::TaxCreated => 'Vergi tanımı oluşturuldu',
            self::TaxUpdated => 'Vergi tanımı güncellendi',
            self::TaxZeroReasonCreated => 'KDV sıfır nedeni oluşturuldu',
            self::TaxZeroReasonUpdated => 'KDV sıfır nedeni güncellendi',
            self::ExchangeRateCreated => 'Kur kaydı oluşturuldu',
            self::ExchangeRateUpdated => 'Kur kaydı güncellendi',
            self::PostingPeriodCreated => 'Muhasebe dönemi oluşturuldu',
            self::PostingPeriodUpdated => 'Muhasebe dönemi güncellendi',
            self::PostingPeriodClosed => 'Muhasebe dönemi kapatıldı',
            self::FileUploaded => 'Dosya yüklendi',
            self::AttachmentDetached => 'Dosya bağlantısı kaldırıldı',
            self::AccountCreated => 'Cari oluşturuldu',
            self::AccountUpdated => 'Cari güncellendi',
            self::AccountProfileUpdated => 'Cari iletişim / adres bilgileri güncellendi',
            self::AccountRecordsUpdated => 'Cari banka / not bilgileri güncellendi',
            self::AccountB2BPolicyUpdated => 'Cari B2B / bayi erişim politikası güncellendi',
            self::ProductCreated => 'Ürün oluşturuldu',
            self::ProductUpdated => 'Ürün güncellendi',
            self::ProductSuppliersUpdated => 'Ürün tedarikçi ilişkileri güncellendi',
            self::CategoryCreated => 'Kategori oluşturuldu',
            self::CategoryUpdated => 'Kategori güncellendi',
            self::UnitCreated => 'Birim oluşturuldu',
            self::UnitUpdated => 'Birim güncellendi',
            self::WarehouseCreated => 'Depo oluşturuldu',
            self::WarehouseLocationCreated => 'Depo lokasyonu oluşturuldu',
            self::StockMovementPosted => 'Stok hareketi işlendi',
            self::QuoteCreated => 'Teklif oluşturuldu',
            self::QuoteUpdated => 'Teklif güncellendi',
            self::QuoteCancelled => 'Teklif iptal edildi',
            self::QuoteRevisionCreated => 'Teklif revizyonu oluşturuldu',
            self::QuoteApproved => 'Teklif revizyonu onaylandı',
            self::QuoteRejected => 'Teklif revizyonu reddedildi',
            self::QuoteConverted => 'Teklif satış siparişine dönüştürüldü',
            self::QuoteFinalizedPdfGenerated => 'Finalized teklif PDF oluşturuldu',
            self::SalesOrderCreated => 'Satış siparişi oluşturuldu',
            self::SalesOrderUpdated => 'Satış siparişi güncellendi',
            self::GoodsReceiptQualityReclassified => 'Mal kabul kalite sınıflandırması güncellendi',
        };
    }
}
