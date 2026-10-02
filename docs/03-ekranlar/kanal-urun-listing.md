# Ekran — Kanal Ürün Listing

## Amaç

Internal product ile external listing eşlemesini ve kanal özel yayın/stok/fiyat ayarlarını yönetmek.

## Temel

- Kanal hesabı
- Internal product
- External Product ID
- External Listing ID
- External SKU
- Aktif/Pasif

## İçerik

- Başlık override
- Açıklama override
- Görsel seti
- Kategori/özellik metadata
- Price override

Override boşsa internal product değerleri kullanılır.

Görsel fallback kanal seti → Ortak'tır.

## Stok

Effective mode:

- Product Varsayılanı
- Stock
- Production
- Manual

Stock:
- satışa katılacak location seçimi
- withhold quantity
- max channel quantity

Production:
- fixed quantity
- lead time

Manual:
- manual quantity

Subcontractor location seçilemez.

## Eylemler

- Mevcut Listing'e Bağla
- Yeni Listing Yayınla
- İçerik Gönder
- Fiyat Gönder
- Stok Gönder
- Pasifleştir
- Sync History
