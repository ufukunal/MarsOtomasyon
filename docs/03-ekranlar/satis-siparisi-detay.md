# Ekran — Satış Siparişi Detayı

**v65 referansı:** `sales_order_new`, `sales_order_detail`.

## Liste kolonları

- Sipariş No
- Cari
- Tarih
- Termin
- Tutar
- Risk
- Rezervasyon
- Sevk
- Fatura
- Kalan
- Durum

## Sekmeler

- Hareketler
- Bilgiler
- Risk
- Rezervasyon
- Sevkiyat
- Faturalama
- Requirement Snapshot
- Dosyalar
- Notlar
- Timeline

Yeni belgede Requirement Snapshot yalnız kaynak/snapshot oluştuğunda gösterilebilir.

## Eylemler

Yeni:

- Kaydet
- Onayla

Detay:

- Hold
- Rezervasyon Yap
- Sevkiyat Oluştur
- Fatura Oluştur
- Kalanı İptal
- Kapat

## Risk sekmesi

Gösterilecek bilgi:

- cari bakiye,
- risk limiti,
- bu sipariş tutarı,
- henüz tahsil edilmemiş portföy çek/senet riski,
- sipariş sonrası projeksiyon,
- limit aşım tutarı.

Limit aşımı **uyarıdır**, kayıt/onay engeli değildir.

## Rezervasyon

Rezervasyon satır bazında seçilir.

- Sistem lokasyonları tarar.
- Yalnız kullanılabilir stok kadar rezerv oluşturur.
- Aynı satır birden fazla lokasyona bölünebilir.
- Karşılanamayan miktar açık kalır.
- Negatif stok izni negatif rezervasyon vermez.

## Kısmi işlem

Satırda kullanıcıya en az:

- Sipariş
- Rezerve
- Sevk
- Fatura
- İptal
- Kalan

değerleri gösterilir.

Sevk/fatura miktarları child document_lines toplamından hesaplanır; ayrı ikinci gerçek kolon değildir.

"Kalanı İptal" kaynak quantity'yi değiştirmez; `cancelled_quantity` artırır ve ilgili rezervi çözer.
