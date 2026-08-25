<?php

namespace App\Modules\Core\Enums;

enum AuditTargetType: string
{
    case Company = 'company';
    case Branch = 'branch';
    case DocumentSequence = 'document_sequence';
    case CompanyMembership = 'company_membership';
    case Role = 'role';
    case Tax = 'tax';
    case TaxZeroReason = 'tax_zero_reason';
    case ExchangeRate = 'exchange_rate';
    case PostingPeriod = 'posting_period';
    case Attachment = 'attachment';
    case Account = 'account';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Firma',
            self::Branch => 'Şube',
            self::DocumentSequence => 'Numara Serisi',
            self::CompanyMembership => 'Kullanıcı Üyeliği',
            self::Role => 'Rol',
            self::Tax => 'Vergi',
            self::TaxZeroReason => 'KDV Sıfır Nedeni',
            self::ExchangeRate => 'Kur Kaydı',
            self::PostingPeriod => 'Muhasebe Dönemi',
            self::Attachment => 'Dosya Bağlantısı',
            self::Account => 'Cari',
            self::Product => 'Ürün',
        };
    }
}
