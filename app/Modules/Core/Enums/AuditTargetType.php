<?php

namespace App\Modules\Core\Enums;

enum AuditTargetType: string
{
    case Company = 'company';
    case DocumentSequence = 'document_sequence';
    case CompanyMembership = 'company_membership';
    case Role = 'role';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Firma',
            self::DocumentSequence => 'Numara Serisi',
            self::CompanyMembership => 'Kullanıcı Üyeliği',
            self::Role => 'Rol',
        };
    }
}
