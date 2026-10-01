# Ekran — Alacak / Tahsilat

**v65 referansı:** `collection`.

## Sekmeler

- Genel
- Bakiye Etkisi
- Makbuz
- Timeline

## Alanlar

v65:

- Şube
- Cari
- İşlem
- Tutar
- Para Birimi
- Kasa/Banka
- Tarih
- Vade
- Evrak No
- Not

Faz 3 satış tarafında para birimi TRY'dir.

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
