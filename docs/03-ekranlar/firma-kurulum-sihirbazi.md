# Ekran — Firma Kurulum Sihirbazı

**Veritabanı: MASTER** (son adımda dönem veritabanı oluşturulur)

## Rota
`/kurulum`

## Yetki
Yalnızca `Yönetici`. İlk kurulumda hiç kullanıcı yoksa açık.

## Adımlar

| # | Adım | İçerik | Zorunlu |
|---|---|---|---|
| 1 | Firma Bilgileri | Kod, kısa ad, resmi unvan, **`db_prefix`**, vergi dairesi/no, adres, logo, para birimi | Evet |
| 2 | İlk Dönem | Yıl seçimi → `{db_prefix}_{yıl}` veritabanı adı gösterilir | Evet |
| 3 | Varsayılanlar | Vade 30 gün, maliyet sapma eşiği %25, KDV %20 | Evet |
| 4 | Lokasyonlar | **En az bir depo.** Şube ve araç isteğe bağlı | Evet |
| 5 | Belge Serileri | Her tür için ön ek ve başlangıç numarası; varsayılanlar önerilir | Hayır |
| 6 | Kullanıcılar | Yönetici zorunlu; diğer roller sonra | Evet |
| 7 | Açılış Verisi | Cari/ürün kartları, açılış stok ve cari bakiyeleri (Excel/JSON) | **Atlanabilir** |
| 8 | Özet ve Tamamla | Seçimler özetlenir, onayla oluşturulur | Evet |

## `db_prefix` — kritik

Dönem veritabanı adı buradan üretilir. Yalnızca harf, rakam, alt çizgi.
**Kayıt sonrası değiştirilemez** — mevcut veritabanı adları buna bağlıdır.
Ekranda bu uyarı açıkça gösterilir.

## Son adımın etki zinciri

```
Tamamla
 1. companies satırı (master)
 2. CREATE DATABASE {db_prefix}_{yıl}
 3. migrate --database=period --path=database/migrations/period
 4. periods satırı (master)
 5. locations, number_series varsayılanları
 6. Yönetici kullanıcı + company_user
 7. Açılış verisi varsa kuyruğa atılır
 8. PeriodContext::use() → yeni firmaya geçilir
 9. activity_log
```

**Dikkat:** `CREATE DATABASE` transaction içinde çalışmaz. 2. adım
başarılı olup 3. adım başarısız olursa **yarım veritabanı kalır**.
Action bunu yakalar, kullanıcıya veritabanı adını vererek elle silme
talimatı verir ve `periods` satırını yazmaz.

## Kabul ölçütü
- Sihirbaz baştan sona tamamlanıyor, veritabanı oluşuyor
- `db_prefix` değiştirilmeye çalışılınca reddediliyor
- Depo eklenmeden sonraki adıma geçilemiyor
- Migration hatasında `periods` satırı yazılmıyor ve kullanıcı bilgilendiriliyor
