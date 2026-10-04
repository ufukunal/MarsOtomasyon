# Ekran — Banka Mutabakatı

## Amaç

K-095'e göre mevcut banka hareketlerini manuel olarak mutabık / mutabık değil işaretlemek.

## Kapsam

İlk Faz 5 sürümünde:

- ekstre dosya importu yok,
- CSV/XLSX parser yok,
- otomatik eşleştirme yok,
- eşleşmeyen ekstre satırından otomatik hareket üretme yok.

## Liste

Filtreler:

- banka hesabı
- tarih
- yön
- cari
- belge tipi
- mutabakat durumu

Kolonlar:

- Tarih
- Banka Hesabı
- Belge No
- Cari
- Açıklama
- Giriş
- Çıkış
- Mutabakat Durumu
- Son Değerlendiren
- Değerlendirme Tarihi

## Eylemler

- Mutabık İşaretle
- Mutabık Değil İşaretle
- Değerlendirmeyi Temizle
- Belgeyi Aç

## Kurallar

Mutabakat yalnız metadata değiştirir:

- amount,
- direction,
- account,
- document,
- contact

değişmez.

Her durum değişikliği audit edilir ve optimistic version kontrolü kullanır.
