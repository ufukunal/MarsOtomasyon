<?php

return [
    [
        'label' => 'Ayarlar',
        'items' => [
            ['label' => 'Şirketler', 'route' => 'settings.companies', 'permission' => 'companies.view'],
            ['label' => 'Kullanıcılar', 'route' => 'settings.users', 'permission' => 'users.view'],
            ['label' => 'Roller', 'route' => 'settings.roles', 'permission' => 'roles.view'],
            ['label' => 'Dönemler', 'route' => 'settings.periods', 'permission' => 'periods.view'],
            ['label' => 'İşlem Geçmişi', 'route' => 'settings.audit', 'permission' => 'audit.view'],
            ['label' => 'Bütünlük Kontrolü', 'route' => 'settings.integrity', 'permission' => 'audit.view'],
            ['label' => 'Yazdırma Profilleri', 'route' => 'settings.print-profiles', 'permission' => 'print_profiles.view'],
            ['label' => 'Şirket Bağlantıları', 'route' => 'settings.company-copy-permissions', 'permission' => 'company_copy_permissions.view'],
        ],
    ],
];
