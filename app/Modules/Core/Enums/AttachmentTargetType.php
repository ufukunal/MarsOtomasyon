<?php

namespace App\Modules\Core\Enums;

enum AttachmentTargetType: string
{
    case Company = 'company';
    case Account = 'account';
    case CrmLead = 'crm_lead';
    case CrmOpportunity = 'crm_opportunity';
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
            self::CrmLead => 'CRM Lead',
            self::CrmOpportunity => 'CRM Fırsat',
            self::Product => 'Ürün',
            self::ProductFamily => 'Ürün ailesi',
            self::Instrument => 'Çek / Senet',
            self::ProductionOrder => 'Üretim emri',
            self::SubcontractOrder => 'Fason sipariş',
        };
    }
}
