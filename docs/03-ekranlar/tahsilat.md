# Ekran — Alacak / Tahsilat

**v65 referansı:** `collection`.

## Sekmeler

- Genel
- Bakiye Etkisi
- Makbuz
- Timeline

## Alanlar

v65 görselinde Şube, Cari, İşlem, Tutar, Para Birimi, Kasa/Banka, Tarih, Vade, Evrak No ve Not alanları görünür.

**Faz 3'te yalnız backing modeli bulunan alanlar aktiftir:**

- Cari
- Tutar
- Kasa/Banka hesabı
- Tarih
- Not
- fatura içinden açıldıysa optional kaynak fatura ilişkisi

Para birimi satış tarafında TRY'dir; serbest döviz tahsilatı Faz 3 kapsamı değildir. İşlem tipi bu ekranda `collection` olarak sabittir.

**Şube, Vade ve Evrak No için Faz 3 veri modelinde onaylı bir persistence alanı yoktur. Bu alanlar sırf v65'te görünüyor diye yeni kolon/tablo uydurularak uygulanmaz.** İleride backing model kararı verilirse ayrıca eklenir.

## Eylem

- Post

## Posting etkisi

Tek transaction:

1. `EnsurePeriodOpen(document_date)`
2. tahsilat numarası gerekiyorsa number series
3. `contact_transactions.credit`
4. seçilen hesap kasa ise `cash_movements.in`
5. seçilen hesap banka ise `bank_movements.in`
6. document posted
7. post-write verify
8. activity_log

Tutarlar eşleşmezse rollback.

## Fatura içinden açılış

Satış faturası detayındaki "Tahsilat" eylemi:

- cariyi hazır seçebilir,
- kaynak faturayı bilgi amaçlı ilişkilendirebilir,
- fakat tahsilatı o faturaya zorunlu bağlamaz.

Ana bakiye sistemi cari hareket toplamıdır.

## Kapsam

Faz 3:

- nakit tahsilat,
- banka tahsilatı.

Çek/senet yaşam döngüsü Faz 5'e aittir.
