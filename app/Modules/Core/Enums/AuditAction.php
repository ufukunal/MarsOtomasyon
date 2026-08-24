<?php

namespace App\Modules\Core\Enums;

enum AuditAction: string
{
    case CompanySettingsUpdated = 'core.company.settings.updated';
    case DocumentSequenceCreated = 'core.document_sequence.created';
    case DocumentSequenceUpdated = 'core.document_sequence.updated';
    case UserCreated = 'core.user.created';
    case UserUpdated = 'core.user.updated';
    case RoleCreated = 'core.role.created';
    case RoleUpdated = 'core.role.updated';

    public function label(): string
    {
        return match ($this) {
            self::CompanySettingsUpdated => 'Firma / sistem ayarları güncellendi',
            self::DocumentSequenceCreated => 'Numara serisi oluşturuldu',
            self::DocumentSequenceUpdated => 'Numara serisi güncellendi',
            self::UserCreated => 'Kullanıcı oluşturuldu',
            self::UserUpdated => 'Kullanıcı üyeliği güncellendi',
            self::RoleCreated => 'Rol oluşturuldu',
            self::RoleUpdated => 'Rol güncellendi',
        };
    }
}
