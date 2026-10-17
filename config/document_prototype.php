<?php

/**
 * Presentation only: financial status transitions and posting remain in their
 * existing Livewire actions. Keep all eight sales/purchase screens consistent.
 */
return [
    'quote' => [
        'title' => 'Satış Teklifi', 'description' => 'Müşteriye sunulacak ürün ve hizmet fiyat teklifi.',
        'save' => 'Teklifi Kaydet', 'date' => 'Teklif Tarihi', 'party' => 'Müşteri / Cari',
        'price' => 'Satış Fiyatı', 'hint' => 'Teklif taslağı stok veya cari hareketi oluşturmaz.',
        'stages' => ['Taslak', 'İç İnceleme', 'Onaylandı'],
    ],
    'sales_order' => [
        'title' => 'Satış Siparişi', 'description' => 'Müşteri siparişi, teslim tarihi ve sevk hazırlığı.',
        'save' => 'Siparişi Kaydet', 'date' => 'Sipariş Tarihi', 'party' => 'Müşteri / Cari',
        'price' => 'Satış Fiyatı', 'hint' => 'Sipariş onayı ve rezervasyonu ayrı işlemlerdir.',
        'stages' => ['Taslak', 'Onaylandı', 'Sevk Bekliyor'],
    ],
    'dispatch' => [
        'title' => 'Satış İrsaliyesi', 'description' => 'Müşteriye sevk edilecek ürünler ve sevkiyat detayları.',
        'save' => 'İrsaliyeyi Kaydet', 'date' => 'İrsaliye Tarihi', 'party' => 'Müşteri / Cari',
        'price' => 'Satış Fiyatı', 'hint' => 'Sevk ve stok hareketi ancak yetkili kesinleştirme işlemiyle oluşur.',
        'stages' => ['Taslak', 'Kontrol', 'Kaydedildi'],
    ],
    'sales_invoice' => [
        'title' => 'Satış Faturası', 'description' => 'Müşteri faturası, KDV ve cari borç kaydı.',
        'save' => 'Faturayı Kaydet', 'date' => 'Fatura Tarihi', 'party' => 'Müşteri / Cari',
        'price' => 'Satış Fiyatı', 'hint' => 'Cari ve vergi hareketleri yalnız kesinleştirmede oluşur.',
        'stages' => ['Taslak', 'Kontrol', 'Kesinleşti'],
    ],
    'purchase_quote' => [
        'title' => 'Alış Teklifi', 'description' => 'Tedarikçiden alınan fiyat tekliflerini karşılaştırma ve değerlendirme.',
        'save' => 'Teklifi Kaydet', 'date' => 'Teklif Tarihi', 'party' => 'Tedarikçi / Cari',
        'price' => 'Alış Fiyatı', 'hint' => 'Teklif stok veya muhasebe hareketi oluşturmaz.',
        'stages' => ['Taslak', 'Tedarikçiden Alındı', 'Değerlendirildi'],
    ],
    'purchase_order' => [
        'title' => 'Satınalma Siparişi', 'description' => 'Tedarikçiye sipariş verme ve fiyat kontrolü.',
        'save' => 'Siparişi Kaydet', 'date' => 'Sipariş Tarihi', 'party' => 'Tedarikçi / Cari',
        'price' => 'Alış Fiyatı', 'hint' => 'Tedarikçi fiyatları ayrı gösterilir, mal girişi henüz yapılmaz.',
        'stages' => ['Taslak', 'Onaylandı', 'Beklenen Mal'],
    ],
    'purchase_dispatch' => [
        'title' => 'Alış İrsaliyesi', 'description' => 'Tedarikçiden gelen irsaliyenin ürün, miktar ve sevk bilgileri.',
        'save' => 'İrsaliyeyi Kaydet', 'date' => 'İrsaliye Tarihi', 'party' => 'Tedarikçi / Cari',
        'price' => 'Alış Fiyatı', 'hint' => 'Fiili depo girişi ve stok kesinleştirmesi Mal Kabul işleminde yapılır.',
        'stages' => ['Taslak', 'Kontrol', 'Kaydedildi'],
    ],
    'supplier_invoice' => [
        'title' => 'Alış Faturası', 'description' => 'Tedarikçi faturası, maliyet ve vergi girişi.',
        'save' => 'Faturayı Kaydet', 'date' => 'Fatura Tarihi', 'party' => 'Tedarikçi / Cari',
        'price' => 'Alış Fiyatı', 'hint' => 'Alış maliyetleri ve KDV kesin kayıtta doğrulanır.',
        'stages' => ['Taslak', 'Kontrol', 'Kesinleşti'],
    ],
];
