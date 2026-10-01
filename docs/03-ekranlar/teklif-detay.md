# Ekran — Teklif Detayı

**v65 referansı:** `quote_new`, `quote_detail`.

## Liste

Teklif listesi kolonları:

- Teklif No
- Cari
- Revizyon
- Tarih
- Geçerlilik
- Para Birimi
- Tutar
- İç Onay
- Müşteri Durumu
- Durum

## Sekmeler

Yeni teklif:

- Hareketler
- Bilgiler
- Konfigürasyon
- Revizyonlar
- Onaylar
- Yorum/Not
- Dosyalar
- PDF
- Timeline

Detay ekranında ayrıca v65'te `Requirement Snapshot` görünür.

## Eylemler

Yeni:

- Kaydet
- İç Onaya Gönder
- Müşteriye Gönder

Detay:

- Yeni Revizyon
- İç Onay
- Müşteriye Gönder
- Onayla
- Siparişe Dönüştür

## İş kuralları

- İç onay `sales.quote.approve` ister.
- Teklif stok, rezervasyon veya cari hareket oluşturmaz.
- Yeni revizyon eski document kaydını değiştirmez.
- Revizyonlar aynı ana teklif numarasını taşır; ilk teklif **Rev.1**, sonraki kayıtlar Rev.2, Rev.3... olarak artar. Rev.0 gösterilmez.
- Yeni revizyon `document_relations.revision_of` ile önceki revizyona bağlanır.
- Konfigürasyon ve requirement snapshot revizyon içinde donar.
- Siparişe dönüştürme yeni `sales_order` document + line kayıtları üretir; teklif satırlarına `source_line_id` ile bağlanır.
- Satır fiyatı normal fiyat çözümleme zincirinden gelir; konfigüratör fiyatı etkilemez.

## Yeni kayıt güvenliği

Yeni teklif başka teklif/cari örnek satırlarıyla dolu açılmaz. Cari ve ürün kullanıcı tarafından seçilir.
