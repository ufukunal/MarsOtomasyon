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
| Satınalma talebi | Yok | Yok | Yok | Confirm |
| Tedarikçi teklifi | Yok | Yok | Yok | Teklif kaydı; seçim ayrı kullanıcı eylemi |
| Satınalma siparişi | Yok | Yok | Yok | Confirm |
| Mal kabul / alış irsaliyesi | **Yok** | Yok | **Yok** | Post; yalnız operasyonel fulfillment |
| Alış faturası | **Giriş** | Yok | **Credit** | Post; moving average güncellenir |
| Tedarikçi ödeme | Yok | Yok | **Debit** | Post + kasa/banka çıkışı |
| Finans virman | Yok | Yok | Yok | Post; source out + target in |
| Kasa sayım farkı | Yok | Yok | Yok | Confirm edilen sayım farkında tek kasa hareketi |

## PostDocument transaction sırası

Her belge bütün adımları çalıştırmaz; yalnız document type effect matrix'inde tanımlı etkiler uygulanır. Sıra değişmez:

1. `EnsurePeriodOpen(document_date)`
2. idempotency key doğrula / processing kaydı
3. gerekliyse `GenerateDocumentNumber(type)` — `lockForUpdate`
4. stok etkili satırlar için `RecordStockMovement`
5. rezerv etkili satırlar için `ConsumeReservation`
6. maliyet etkili stok girişinde `UpdateMovingAverage`
7. cari etkili belge için `contact_transactions`
8. kasa/banka etkili tahsilatta ilgili movement
9. status/posted_at/posted_by snapshot
10. aynı transaction içinde post-write verify
11. period activity_log
12. idempotency result = done

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

### Faz 5 finans

- supplier_payment K-093 gereği supplier contact debit + cash/bank out üretir; kaynak alış faturası ilişkisi bilgi amaçlıdır.
- finance_transfer K-092 gereği aynı para birimli source out + target in üretir; cari etkisi yoktur.
- cash_count_adjustment K-094 gereği yalnız confirmed kasa sayım farkından üretilir.
- bank reconciliation K-095 gereği finansal belge değildir; mevcut bank movement metadata'sını değiştirir ve audit yazar.
- Çek/senet yaşam döngüsü `security_events` üzerinden yürür; K-082/K-096 cari/finans etkileri iş kuralları 37–38'de tanımlıdır.

### Faz 4 satınalma

K-086 gereği purchase_request → supplier_quote → purchase_order → goods_receipt → purchase_invoice zinciri esnektir; ara belgeler zorunlu değildir.

- purchase_request stok/cari etkisizdir.
- supplier_quote stok/cari etkisizdir; K-090/K-091 seçimi kullanıcı yapar.
- purchase_order stok/cari etkisizdir.
- goods_receipt K-087 gereği stok/cari/maliyet etkisiz operasyon kaydıdır.
- purchase_invoice post edildiğinde stock in + supplier contact credit + moving average aynı transaction içinde oluşur.
- Faz 4'te supplier payment yoktur; K-089 gereği Faz 5'tedir.

## Post-write doğrulama

Transaction commit edilmeden:

- belge toplamları hesap motoruyla eşleşmeli,
- üretilmesi gereken stock movement sayısı/miktarı eşleşmeli,
- tüketilen rezerv miktarı kaynak rezervi aşmamalı,
- cari etkili belge için document_id ile tek contact transaction olmalı,
- `contact_debit_credit` için üretilen cari yön typed posting context ile aynı olmalı,
- tahsilat için kasa/banka movement tutarı contact transaction tutarıyla aynı olmalı,
- purchase_invoice için stock movement miktarı/base unit cost, moving average ve supplier credit etkisi eşleşmeli,
- goods_receipt için stock/contact movement bulunmamalı,
- finance_transfer için source out + target in tam çift olmalı,
- supplier_payment için contact debit + finans out tutarı eşleşmeli,
- cash_count_adjustment yalnız ilgili confirmed cash_count farkıyla eşleşmeli.

Uyuşmazlık `DomainException`/integrity exception ile rollback üretir.
