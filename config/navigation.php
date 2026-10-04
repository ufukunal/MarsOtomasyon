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
            ['label' => 'Başka Şirketten Aktar', 'route' => 'company-copy.index', 'permission' => 'company_copy_permissions.view'],
            ['label' => 'İçe Aktarma', 'route' => 'imports.index', 'permission' => 'imports.create'],
        ],
    ],
    [
        'label' => 'Stok',
        'items' => [
            ['label' => 'Stok Durumu', 'route' => 'stock.status', 'permission' => 'stock.view'],
            ['label' => 'Stok Hareketleri', 'route' => 'stock.movements', 'permission' => 'stock.view'],
            ['label' => 'Transferler', 'route' => 'stock.transfers.index', 'permission' => 'transfers.view'],
            ['label' => 'Ambar Fişleri', 'route' => 'stock.warehouse-slips.index', 'permission' => 'warehouse_slips.view'],
            ['label' => 'Stok Sayımları', 'route' => 'stock.counts.index', 'permission' => 'stock_counts.view'],
            ['label' => 'Karantina', 'route' => 'stock.quarantine.index', 'permission' => 'quarantine.view'],
            ['label' => 'Rezervasyonlar', 'route' => 'stock.reservations.index', 'permission' => 'reservations.view'],
            ['label' => 'Açılış Bakiyesi', 'route' => 'stock.opening.index', 'permission' => 'imports.create'],
        ],
    ],
    [
        'label' => 'Satış',
        'items' => [
            ['label' => 'Teklifler', 'route' => 'sales.quotes.index', 'permission' => 'quotes.view'],
            ['label' => 'Satış Siparişleri', 'route' => 'sales.orders.index', 'permission' => 'sales_orders.view'],
            ['label' => 'İrsaliyeler', 'route' => 'sales.dispatches.index', 'permission' => 'dispatches.view'],
            ['label' => 'Satış Faturaları', 'route' => 'sales.invoices.index', 'permission' => 'sales_invoices.view'],
            ['label' => 'Proformalar', 'route' => 'sales.proformas.index', 'permission' => 'proformas.view'],
            ['label' => 'Araç Sıcak Satış', 'route' => 'sales.vehicle-hot-sale', 'permission' => 'sales_invoices.create'],
        ],
    ],
    [
        'label' => 'Alış',
        'items' => [
            ['label' => 'Satınalma Siparişleri', 'route' => 'purchases.orders.index', 'permission' => 'purchase_orders.view'],
            ['label' => 'Mal Kabul', 'route' => 'purchases.receipts.index', 'permission' => 'goods_receipts.view'],
            ['label' => 'Alış Faturaları', 'route' => 'purchases.invoices.index', 'permission' => 'supplier_invoices.view'],
            ['label' => 'Ödeme', 'route' => 'purchases.payments.create', 'permission' => 'payments.view'],
            ['label' => 'Tedarikçi Performansı', 'route' => 'purchases.supplier-performance', 'permission' => 'supplier_performance.view'],
        ],
    ],
    [
        'label' => 'Finans',
        'items' => [
            ['label' => 'Tahsilat', 'route' => 'finance.collections.create', 'permission' => 'collections.view'],
            ['label' => 'Cari Borç / Alacak', 'route' => 'finance.contact-debit-credit', 'permission' => 'contacts.update'],
            ['label' => 'Cari Yaşlandırma', 'route' => 'finance.contact-aging', 'permission' => 'contact_aging.view'],
            ['label' => 'Kasa / Banka Hesapları', 'route' => 'finance.accounts', 'permission' => 'cash_accounts.view'],
            ['label' => 'Kasa / Banka Hareketleri', 'route' => 'finance.operations', 'permission' => 'finance_movements.view'],
            ['label' => 'Banka Ekstresi / Mutabakat', 'route' => 'finance.bank-statements', 'permission' => 'bank_statements.view'],
            ['label' => 'Çek / Senet', 'route' => 'finance.securities', 'permission' => 'securities.view'],
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
