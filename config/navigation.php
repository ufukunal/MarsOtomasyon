<?php

return [
    [
        'label' => 'Kartlar',
        'items' => [
            ['label' => 'Lokasyonlar', 'route' => 'locations.index', 'permission' => 'locations.view'],
            ['label' => 'Birimler', 'route' => 'units.index', 'permission' => 'units.view'],
            ['label' => 'Kategoriler', 'route' => 'categories.index', 'permission' => 'product_categories.view'],
            ['label' => 'Markalar', 'route' => 'brands.index', 'permission' => 'brands.view'],
            ['label' => 'Cariler', 'route' => 'contacts.index', 'permission' => 'contacts.view'],
            ['label' => 'Ürünler', 'route' => 'products.index', 'permission' => 'products.view'],
            ['label' => 'Varyant Grupları', 'route' => 'variant-groups.detail', 'permission' => 'variant_groups.view'],
            ['label' => 'Fiyat Listeleri', 'route' => 'price-lists.index', 'permission' => 'price_lists.view'],
        ],
    ],
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
