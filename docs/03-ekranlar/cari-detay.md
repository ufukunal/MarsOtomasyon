# Ekran — Cari Detay

## Rota
`/cariler/{contact}`

## Yetki
`contacts.view`. Düzenleme `contacts.update`.

## Başlık
Cari unvanı. Breadcrumb: Cariler › Cari Listesi

## Üst göstergeler (KPI)
| Gösterge | Kaynak | Not |
|---|---|---|
| Cari Bakiye | `contact_transactions` toplamı | Faz 3'e kadar 0 |
| Yıllık Satış | satış faturaları toplamı | Faz 3 |
| Açık Sipariş | kapanmamış sipariş sayısı ve tutarı | Faz 3 |
| Risk Limiti | `contacts.risk_limit` | kullanım yüzdesiyle |

## Eylem menüleri
- **Alım İşlemleri**: Satınalma Siparişi, Alış Faturası, Mal Kabul, Ödeme
- **Satış İşlemleri**: Yeni Teklif, Yeni Sipariş, Satış Faturası, Tahsilat, Sevkiyat
- **İade İşlemleri**: Satış İadesi, Alış İadesi (Faz 6)
- **Yazdır**: Cari Kartı, Ekstre, sekme bazlı çıktılar (Faz 10)
- **Geri**

Faz 1'de bu menüler görünür ama maddeler ilgili faz gelene kadar pasiftir.

## Sekmeler
Genel Bakış · Faturalar · Siparişler · Teklifler · Cari Hareketler ·
Ürün Analizi · Yıllık Performans · Adresler · İletişim · Fatura Bilgileri ·
Sevk Bilgileri · Banka Bilgileri · Dosyalar

Faz 1'de dolu olanlar: Genel Bakış, Adresler, İletişim, Banka Bilgileri, Dosyalar.

## Genel Bakış sekmesi — alanlar

| Alan | Kaynak | Kural |
|---|---|---|
| Cari Kodu | `code` | salt okunur |
| Unvan | `title` | zorunlu |
| Kategori | çoklu ilişki | en az bir tane |
| Vergi Dairesi / No | `tax_office`, `tax_number` | benzersizlik uyarısı |
| Vade (gün) | `term_days` | boşsa şirket varsayılanı (ipucu göster) |
| İskonto (%) | `discount_rate` | belgelere otomatik gelir |
| Risk Limiti | `risk_limit` | aşımda uyarı, engel yok |
| Durum | `is_active` | pasif cari belgede seçilemez |

## Etki zinciri — "Kaydet"

```
Kaydet
 → doğrulama (zorunlu alanlar, kod benzersizliği)
 → Contact güncellenir
 → activity_log'a eski/yeni değer yazılır
 → bildirim: "Cari kaydedildi"
```

Kod değiştirilemez; değiştirme denemesi doğrulamada reddedilir.

## Etki zinciri — "Pasife Al"
```
 → hareketi var mı kontrol edilir
 → varsa: silinemez, yalnızca pasife alınır
 → pasif cari yeni belgelerde seçilemez, mevcut belgeler etkilenmez
```
