# Şirketler arası kopyalama

## Kural

Hedef şirkette çalışan kullanıcı, `company_copy_permissions` tablosunda izin varsa
kaynak şirketin cari veya ürün kartlarını listeler ve **tek tıkla kopyalar**.

Kopyalama **yeni kayıt** oluşturur. Canlı bağ kurulmaz.

## Akış

1. Kullanıcı hedef şirkette "Başka şirketten aktar" ekranını açar
2. İzinli kaynak şirketler listelenir (`company_copy_permissions` üzerinden)
3. Kaynak seçilir, kartlar listelenir (`withoutGlobalScopes` + elle
   `where company_id = kaynak`)
4. Seçilen kartlar kopyalanır:
   - Yeni `id`, hedefin `company_id`'si
   - `source_company_id` ve `source_record_id` doldurulur
   - Kod çakışırsa sonuna `-2` eklenir, kullanıcıya bildirilir
   - Bakiye, hareket, belge **kopyalanmaz** — yalnızca kart bilgisi
5. `audit_log`'a kayıt düşer

## Güvenlik

`withoutGlobalScopes()` yalnızca burada ve yalnızca izin doğrulandıktan
sonra kullanılır. İzin kontrolü Action'ın ilk satırıdır:

```php
abort_unless(
    CompanyCopyPermission::where('source_company_id', $sourceId)
        ->where('target_company_id', CompanyContext::id())
        ->where('type', $type)
        ->where('is_active', true)
        ->exists(),
    403
);
```

## Güncelleme kontrolü

Kaynak kayıt sonradan değişirse hedef otomatik güncellenmez.
"Kaynakta değişti mi" işlemi kopyalanan kayıtları kaynakla karşılaştırır
ve farkı listeler; kullanıcı isterse günceller.
