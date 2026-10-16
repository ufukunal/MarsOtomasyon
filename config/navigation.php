<?php

// UI groups follow approved v65; permissions and routes are preserved.
// Only available, authorized modules appear. Never add fake prototype routes.
return [
    [
        'label' => 'Ana Sayfa',
        'items' => [
            ['label' => 'Ana Sayfa', 'route' => 'home', 'permission' => null],
        ],
    ],
    [
        'label' => 'Cariler',
        'items' => [
            ['label' => 'Cari Listesi', 'route' => 'contacts.index', 'permission' => 'contacts.view'],
            ['label' => 'Cari Virman', 'route' => 'finance.contact-transfers', 'permission' => 'contact_transfers.view'],
            ['label' => 'Başka Şirketten Aktar', 'route' => 'company-copy.index', 'permission' => 'company_copy_permissions.view'],
        ],
    ],
    [
        'label' => 'Ürün / Stok',
        'items' => [
            ['label' => 'Ürün Listesi', 'route' => 'products.index', 'permission' => 'products.view'],
            ['label' => 'Stok Durumu', 'route' => 'stock.status', 'permission' => 'stock.view'],
            ['label' => 'Stok Hareketleri', 'route' => 'stock.movements', 'permission' => 'stock.view'],
            ['label' => 'Depolar', 'route' => 'locations.index', 'permission' => 'locations.view'],
            ['label' => 'Depo Transferleri', 'route' => 'stock.transfers.index', 'permission' => 'transfers.view'],
            ['label' => 'Yeni Depo Transferi', 'route' => 'stock.transfers.create', 'permission' => 'transfers.create'],
            ['label' => 'Stok Sayımları', 'route' => 'stock.counts.index', 'permission' => 'stock_counts.view'],
            ['label' => 'Yeni Stok Sayımı', 'route' => 'stock.counts.create', 'permission' => 'stock_counts.create'],
            ['label' => 'Hızlı Stok Sayımı', 'route' => 'stock.counts.quick', 'permission' => 'stock_counts.create'],
            ['label' => 'Stok Sayım Listesi', 'route' => 'stock.counts.results', 'permission' => 'stock_counts.view'],
            ['label' => 'Kategoriler', 'route' => 'categories.index', 'permission' => 'product_categories.view'],
            ['label' => 'Markalar', 'route' => 'brands.index', 'permission' => 'brands.view'],
            ['label' => 'Varyantlar', 'route' => 'variant-groups.detail', 'permission' => 'variant_groups.view'],
            ['label' => 'Birimler', 'route' => 'units.index', 'permission' => 'units.view'],
            ['label' => 'Rezervasyonlar', 'route' => 'stock.reservations.index', 'permission' => 'reservations.view'],
            ['label' => 'Karantina / Bloke', 'route' => 'stock.quarantine.index', 'permission' => 'quarantine.view'],
            ['label' => 'Stok Düzeltme Fişleri', 'route' => 'stock.warehouse-slips.index', 'permission' => 'warehouse_slips.view'],
            ['label' => 'Stok Maliyeti', 'route' => 'stock.costs', 'permission' => 'cost.view', 'permission_secondary' => 'stock.view'],
            ['label' => 'Açılış Bakiyesi', 'route' => 'stock.opening.index', 'permission' => 'imports.create'],
            ['label' => 'İçe Aktarma', 'route' => 'imports.index', 'permission' => 'imports.create'],
        ],
    ],
    [
        'label' => 'Satış',
        'items' => [
            ['label' => 'Teklifler', 'route' => 'sales.quotes.index', 'permission' => 'quotes.view'],
            ['label' => 'Satış Siparişleri', 'route' => 'sales.orders.index', 'permission' => 'sales_orders.view'],
            ['label' => 'Sevkiyat / İrsaliyeler', 'route' => 'sales.dispatches.index', 'permission' => 'dispatches.view'],
            ['label' => 'Satış Faturaları', 'route' => 'sales.invoices.index', 'permission' => 'sales_invoices.view'],
            ['label' => 'Proformalar', 'route' => 'sales.proformas.index', 'permission' => 'proformas.view'],
            ['label' => 'Araç Sıcak Satış', 'route' => 'sales.vehicle-hot-sale', 'permission' => 'sales_invoices.create'],
        ],
    ],
    [
        'label' => 'Ambar',
        'items' => [
            ['label' => 'Ambar Fişleri', 'route' => 'warehouse.transport.index', 'permission' => 'warehouse_slips.view'],
            ['label' => 'Yeni Ambar Fişi', 'route' => 'warehouse.transport.create', 'permission' => 'warehouse_slips.create'],
        ],
    ],
    [
        'label' => 'Alış',
        'items' => [
            ['label' => 'Alış Teklifleri', 'route' => 'purchases.quotes.index', 'permission' => 'purchase_quotes.view'],
            ['label' => 'Satınalma Siparişleri', 'route' => 'purchases.orders.index', 'permission' => 'purchase_orders.view'],
            ['label' => 'Mal Kabul', 'route' => 'purchases.receipts.index', 'permission' => 'goods_receipts.view'],
            ['label' => 'Alış Faturaları', 'route' => 'purchases.invoices.index', 'permission' => 'supplier_invoices.view'],
            ['label' => 'Ödeme', 'route' => 'purchases.payments.create', 'permission' => 'payments.view'],
            ['label' => 'Tedarikçi Performansı', 'route' => 'purchases.supplier-performance', 'permission' => 'supplier_performance.view'],
        ],
    ],
    [
        'label' => 'Üretim',
        'items' => [
            ['label' => 'Reçeteler', 'route' => 'production.recipes', 'permission' => 'production_recipes.view'],
            ['label' => 'Üretim Emirleri', 'route' => 'production.orders', 'permission' => 'production_orders.view'],
            ['label' => 'Fason Üretim', 'route' => 'production.subcontracting', 'permission' => 'subcontracting.view'],
        ],
    ],
    [
        'label' => 'Kasa / Banka',
        'items' => [
            ['label' => 'Tahsilat', 'route' => 'finance.collections.create', 'permission' => 'collections.view'],
            ['label' => 'Cari Borç / Alacak', 'route' => 'finance.contact-debit-credit', 'permission' => 'contacts.update'],
            ['label' => 'Cari Yaşlandırma', 'route' => 'finance.contact-aging', 'permission' => 'contact_aging.view'],
            ['label' => 'Kasa / Banka Hesapları', 'route' => 'finance.accounts', 'permission' => 'cash_accounts.view'],
            ['label' => 'Kasa / Banka Hareketleri', 'route' => 'finance.operations', 'permission' => 'finance_movements.view'],
            ['label' => 'Banka Ekstresi / Mutabakat', 'route' => 'finance.bank-statements', 'permission' => 'bank_statements.view'],
        ],
    ],
    [
        'label' => 'Çek / Senet',
        'items' => [
            ['label' => 'Çek / Senet', 'route' => 'finance.securities', 'permission' => 'securities.view'],
        ],
    ],
    [
        'label' => 'İade / RMA',
        'items' => [
            ['label' => 'İade Merkezi', 'route' => 'returns.center', 'permission' => 'returns.view'],
        ],
    ],
    [
        'label' => 'İthalat',
        'items' => [
            ['label' => 'İthalat Dosyaları', 'route' => 'imports.shipments', 'permission' => 'import_shipments.view'],
        ],
    ],
    [
        'label' => 'E-Ticaret',
        'items' => [
            ['label' => 'Kanal Hesapları', 'route' => 'channels.accounts', 'permission' => 'channel_accounts.view'],
            ['label' => 'Kanal Listingleri', 'route' => 'channels.listings', 'permission' => 'channel_listings.view'],
            ['label' => 'Sync Merkezi', 'route' => 'channels.sync', 'permission' => 'channel_sync.view'],
        ],
    ],
    [
        'label' => 'Raporlar',
        'items' => [
            ['label' => 'Rapor Merkezi', 'route' => 'reports.center', 'permission' => 'reports.view'],
            ['label' => 'Çok Dönemli Rapor', 'route' => 'reports.consolidated', 'permission' => 'reports.consolidated'],
            ['label' => 'Dışa Aktarma Merkezi', 'route' => 'reports.exports', 'permission' => 'reports.view'],
            ['label' => 'Belge Şablonları', 'route' => 'reports.templates', 'permission' => 'document_templates.view'],
            ['label' => 'Yazdırma Geçmişi', 'route' => 'reports.print-history', 'permission' => 'reports.view'],
        ],
    ],
    [
        'label' => 'Ayarlar',
        'items' => [
            ['label' => 'Şirketler', 'route' => 'settings.companies', 'permission' => 'companies.view'],
            ['label' => 'Kullanıcılar', 'route' => 'settings.users', 'permission' => 'users.view'],
            ['label' => 'Roller', 'route' => 'settings.roles', 'permission' => 'roles.view'],
            ['label' => 'Dönemler', 'route' => 'settings.periods', 'permission' => 'periods.view'],
            ['label' => 'Dönem Devri', 'route' => 'settings.period-carry', 'permission' => 'periods.view'],
            ['label' => 'İşlem Geçmişi', 'route' => 'settings.audit', 'permission' => 'audit.view'],
            ['label' => 'Bütünlük Kontrolü', 'route' => 'settings.integrity', 'permission' => 'audit.view'],
            ['label' => 'Operasyon Merkezi', 'route' => 'settings.operations', 'permission' => 'audit.view'],
            ['label' => 'Yazdırma Profilleri', 'route' => 'settings.print-profiles', 'permission' => 'print_profiles.view'],
            ['label' => 'Şirket Bağlantıları', 'route' => 'settings.company-copy-permissions', 'permission' => 'company_copy_permissions.view'],
        ],
    ],
];
