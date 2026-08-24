# 16 — Uygulama Sırası ve Milestone'lar

V16.3 tasarımı geliştirme sırasını belirleyen kabul referansıdır. Amaç bütün ekranları boş kabuk olarak açmak değil, her milestone sonunda çalışan ve test edilen dikey bir iş akışı teslim etmektir.

## M0 — Repository ve temel kalite kapıları
- Laravel 13 / PHP 8.5 iskeleti
- PostgreSQL 18 CI
- Valkey bağlantısı
- formatter/static analysis/test komutları
- `.env.example`, secret policy
- migration smoke test
- temel health endpoint

## M1 — Core + UI Shell + Ayarlar
- auth/session
- kullanıcı/rol/yetki
- firma/şube bağlamı
- numaralandırma
- vergi/döviz/dönem
- audit log
- V16.3 sidebar/topbar/workspace tabs/global search/command palette

## M2 — Cari
- cari CRUD/detail/edit
- iletişim/yetkililer
- sevk/adres
- cari iskonto/risk limiti
- B2B erişimi
- `account_transactions`
- bakiye/ekstre

## M3 — Ürün/Stok/Depo
- ürün CRUD/detail/edit
- barkod/QR arama
- depo/lokasyon
- `stock_movements`
- rezervasyon
- transfer
- sayım

## M4 — Satış
`Teklif → Satış Siparişi → İrsaliye/Sevkiyat → Satış Faturası → İade`

Zorunlu: kısmi sevk/faturalama, kalan miktar, KDV sıfırlama, atomik posting ve idempotency.

## M5 — Alış
`Satınalma Siparişi → Mal Kabul → Alış Faturası → Alış İadesi`

Mal kabul fiziksel stok girişidir; alış faturası cari borç etkisidir.

## M6 — Kasa/Banka
- tahsilat
- ödeme
- gider
- kasa/banka hareketleri
- virman
- ekstre Excel/CSV/MT940 import
- mutabakat
- dinamik ödeme tipi alanları

## M7 — Çek/Senet
- alınan/verilen kayıtlar
- state transition
- portföy/konum geçmişi
- ön/arka görseller
- settlement concurrency testleri

## M8 — Raporlar
Hazır rapor merkezi + Excel/CSV/PDF/yazdırma. İlk sürümde generic report designer yok.

## M9 — Üretim/Fason
`Reçete → Üretim Emri → Malzeme Çıkışı → Mamul Girişi → Tamamla`

Fason aynı stok motorunu kullanır.

## M10 — İade/RMA
Satış/alış/e-ticaret iadelerinin kontrollü akışı.

## M11 — E-Ticaret/B2B
Integration Core + WooCommerce + Trendyol + Mars B2B. Webhook, retry, idempotency, sync conflict ve error center zorunlu.

## M12 — İthalat
Konteyner/sevkiyat, koli-malzeme eşleme, maliyet dağıtımı, üretim listeleri, yükleme/ağırlık simülasyonu.

## M13 — İletişim/API/Dosyalar
SMS/e-posta/WhatsApp provider adaptörleri, template/delivery/retry, dosya ekleri ve scanner agent.

## M14 — Operasyon
Backup/restore drill, veri migrasyonu, observability, hardening, performance ve final audit.

## Milestone çıkış kapısı
Her milestone için:
`schema → use-case → V16.3 UI → authorization → invariant tests → PostgreSQL CI → observability`

Bunlardan biri yoksa milestone tamamlanmış sayılmaz.
