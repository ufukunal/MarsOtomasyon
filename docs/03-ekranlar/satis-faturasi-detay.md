# Ekran — Satış Faturası Detayı

**v65 referansı:** `sales_invoice_list`, `sales_invoice_new`, `sales_invoice_detail`.

## Liste kolonları

- Fatura No
- Cari
- Tarih
- Vade
- Para Birimi
- Ara Toplam
- KDV
- Genel Toplam
- Tahsilat
- Kalan
- Durum

v65'te görülen E-Belge kolonu proje kapsamı dışındadır ve Faz 3'e alınmaz.

## Sekmeler

Faz 3:

- Hareketler
- Bilgiler
- Ödemeler
- Cari Bakiye
- Notlar
- Dosyalar
- PDF
- Timeline

v65'teki `E-Belge` sekmesi kapsam dışıdır. `Adjustment` yerine kesinleşmiş belge düzeltmesi K-016/K-079 uyarınca ters kayıtla yapılır.

## Eylemler

Yeni:

- Kaydet
- Post Et

Detay:

- Tahsilat
- Reverse
- PDF

## Posting etkisi

### İrsaliyeden fatura

- cari `debit` hareketi oluşur,
- stok ikinci kez düşmez,
- `dispatch_to_invoice` ilişkisi oluşur,
- child line source_line_id dispatch satırını gösterir.

### Doğrudan fatura

- cari `debit`,
- `RecordStockMovement(out)`,
- gerekiyorsa rezerv tüketimi

aynı transaction içinde oluşur.

## Hesap

- Birim fiyat KDV hariç.
- Satır ve belge iskontosu KDV'den önce.
- KDV oran grubu bazında tek sefer hesaplanır.
- Genel toplam 2 hane half-up.
- `rounding_difference` saklanır.
- "Tümüne KDV uygula" / "KDV temizle" taslakta kullanılabilir.

## Tahsilat / kalan göstergesi

"Tahsilat" ve "Kalan" **muhasebe settlement alanı değildir**. Aynı cari için `BuildContactAging` motorunun o anki `as_of` tarihine göre yaptığı sanal FIFO dağıtımında bu faturanın debit hareketine uygulanan credit tutarı bilgi amaçlı gösterilir:

```
tahsilat_gosterim = FIFO applied_credit
kalan_gosterim    = invoice grand_total - FIFO applied_credit
```

- Kalıcı invoice-settlement tablosu veya invoice üzerinde paid/remaining kolonu oluşturulmaz.
- Optional `collection_source` ilişkileri bu hesabı değiştirmez.
- Cari gerçek bakiye daima `contact_transactions` toplamıdır.
- Ödemeler sekmesi, varsa bilgi amaçlı kaynak ilişkilerini gösterebilir; "bu ödeme bu faturayı kesin kapattı" anlamı taşımaz.
