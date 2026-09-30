# İşlem geçmişi (audit)

spatie/laravel-activitylog kullanılır, `activity_log` tablosuna
`company_id` kolonu eklenir.

## Ek kolon

```php
Schema::table('activity_log', function (Blueprint $table) {
    $table->foreignId('company_id')->nullable()->after('id')->constrained();
    $table->index(['company_id', 'created_at']);
});
```

## Loglanacaklar

| Olay | Kayıt |
|---|---|
| Kart oluşturma / değiştirme | eski ve yeni değerler |
| Belge kesinleştirme | belge no, tutar, cari |
| Belge iptali / ters kayıt | gerekçe |
| Dönem kapatma / açma | kim, ne zaman, gerekçe |
| Yetki değişikliği | kim kime hangi rolü verdi |
| Maliyet sapma uyarısı geçildiğinde | ürün, beklenen, girilen |
| Şirketler arası kopyalama | kaynak, hedef, kayıt |
| Giriş / çıkış / başarısız giriş | IP |

## Loglanmayacaklar

Liste görüntüleme, arama, rapor açma. Gürültü yaratır, değer üretmez.

## Saklama

Sınırsız. `audit_log` temizlenmez; gerekirse arşivlenir.
