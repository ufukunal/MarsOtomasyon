# Belge yaşam döngüsü

## Temel durum ilkesi

Taslak belge düzenlenebilir ve `version` optimistic lock ile korunur. Kesinleşmiş/posted belge yerinde değiştirilmez; düzeltme ters kayıt veya yeni ilişkili belge ile yapılır.

Taslak belge numarasız olabilir ve fiziksel silinebilir. Kesinleşmiş belge silinmez.

## Etki matrisi

| Tür | Stok | Rezerv | Cari | Numara / kesinlik |
|---|---|---|---|---|
| Teklif | Yok | Yok | Yok | İlk dış/iş onay akışında ana teklif numarası; revizyon aynı numara + Rev.N |
| Satış siparişi | Yok | İsteğe/satır bazlı | Yok | Onaylanınca numara/confirmed |
| İrsaliye | **Çıkış** | İlgili rezervi çözer | Yok | Post |
| Satış faturası — irsaliyeden | Tekrar stok yok | Yok | **Debit** | Post |
| Satış faturası — doğrudan | **Çıkış** | Varsa ilişkili rezerv çözümü | **Debit** | Post |
| Proforma | Yok | Yok | Yok | Oluşturulurken period `proforma` serisinden numara alır; finansal posting etkisi yoktur |
| Tahsilat | Yok | Yok | **Credit** | Post + kasa/banka girişi |
| Cari Borç/Alacak Fişi | Yok | Yok | **Typed context: Debit veya Credit** | Post |

## PostDocument transaction sırası

Her belge bütün adımları çalıştırmaz; yalnız document type effect matrix'inde tanımlı etkiler uygulanır. Sıra değişmez:

1. `EnsurePeriodOpen(document_date)`
2. idempotency key doğrula / processing kaydı
3. gerekliyse `GenerateDocumentNumber(type)` — `lockForUpdate`
4. stok etkili satırlar için `RecordStockMovement`
5. rezerv etkili satırlar için `ConsumeReservation`
6. cari etkili belge için `contact_transactions`
7. kasa/banka etkili tahsilatta ilgili movement
8. status/posted_at/posted_by snapshot
9. aynı transaction içinde post-write verify
10. period activity_log
11. idempotency result = done

Hepsi:

```php
DB::connection('period')->transaction(function () {
    // zincir
}, attempts: 3);
```

## Belge türü ayrıntıları

### Teklif

v65 eylemleri: Kaydet, İç Onaya Gönder, Müşteriye Gönder; detayda Yeni Revizyon, İç Onay, Müşteriye Gönder, Onayla, Siparişe Dönüştür.

- İç onay `sales.quote.approve` iznine bağlıdır.
- Yeni revizyon eski kaydı değiştirmez; ayrı document kaydıdır.
- Aynı ana number, artan revision_no.
- `document_relations.revision_of` zinciri kurulur.
- Requirement/configuration snapshot geçmiş revizyonda donar.

### Satış siparişi

v65: Kaydet, Onayla; detayda Hold, Rezervasyon Yap, Sevkiyat Oluştur, Fatura Oluştur, Kalanı İptal, Kapat.

- Confirmed sipariş fiziksel stok düşürmez.
- Rezervasyon ayrı eylemdir ve satır bazında seçilir.
- Risk limiti aşımı uyarıdır; onayı bloklamaz.
- Tam karşılanan veya kalan miktarı iptal edilen sipariş `closed` olur.

### İrsaliye

- Fiziksel sevk belgesidir.
- Post edildiğinde stok çıkar.
- Cari borçlandırmaz.
- Kaynak sipariş rezervini ilgili miktar kadar çözer.

### Satış faturası

- Cari borçlandırır.
- Kaynak irsaliye satırı olan miktar için stok ikinci kez düşmez.
- Kaynaksız/doğrudan satır stok çıkışı üretir.
- Posted fatura mutate edilmez.

### Cari Borç / Alacak Fişi

- Yön belge tipinden türetilmez; doğrulanmış `debit|credit` typed posting context ile `PostDocument`a verilir.
- Tek `contact_transactions` hareketi üretir.
- Gerekçe zorunludur ve period audit'e yazılır.

### Tahsilat

- `contact_transactions.credit` üretir.
- Kasa seçildiyse `cash_movements.in`, banka seçildiyse `bank_movements.in`.
- Faturaya zorunlu settlement dağıtımı yoktur.

## Post-write doğrulama

Transaction commit edilmeden:

- belge toplamları hesap motoruyla eşleşmeli,
- üretilmesi gereken stock movement sayısı/miktarı eşleşmeli,
- tüketilen rezerv miktarı kaynak rezervi aşmamalı,
- cari etkili belge için document_id ile tek contact transaction olmalı,
- `contact_debit_credit` için üretilen cari yön typed posting context ile aynı olmalı,
- tahsilat için kasa/banka movement tutarı contact transaction tutarıyla aynı olmalı.

Uyuşmazlık `DomainException`/integrity exception ile rollback üretir.
