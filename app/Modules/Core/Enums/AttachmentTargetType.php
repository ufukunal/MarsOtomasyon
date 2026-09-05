<?php

namespace App\Modules\Core\Enums;

enum AttachmentTargetType: string
{
    case Company = 'company';
    case Account = 'account';
    case Product = 'product';
    case ProductFamily = 'product_family';
    case Instrument = 'instrument';
    case ProductionOrder = 'production_order';
    case SubcontractOrder = 'subcontract_order';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Firma',
            self::Account => 'Cari',
            self::Product => 'Ürün',
            self::ProductFamily => 'Ürün ailesi',
            self::Instrument => 'Çek / Senet',
            self::ProductionOrder => 'Üretim emri',
            self::SubcontractOrder => 'Fason sipariş',
        };
    }
}
