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
        };
    }
}
