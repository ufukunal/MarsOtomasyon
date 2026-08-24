# 13 — UI/UX ve Raporlama

## Otorite
Kullanıcı-visible referans: **MarsOtomasyon V16.3 — Genel Tasarım Temizliği**. Ayrıntılı sözleşme `26_V16_3_TASARIM_UYUMU.md`.

## Shell
- 252px sol navigasyon
- üst toolbar
- workspace tabs
- global arama
- hızlı işlemler / command palette
- sticky sayfa ve form aksiyonları
- drawer/modal/toast
- keyboard/scanner odaklı kullanım

## Workspace tabs
- dirty olmayan sekme direkt kapanır
- dirty sekme kapanırken kaydet/vazgeç uyarısı
- dirty dot görünür
- save dirty state'i temizler
- readonly sekme direkt kapanır
- search typing dirty oluşturmaz

## Ekran deseni
Listeler:
`başlık → hızlı aksiyon → filtre → tablo → pagination/export`.

Formlar domain'e özgüdür. Generic her modüle aynı form/table renderer yaklaşımı acceptance değildir.

## Detail/Edit
Detail readonly bilgi merkezi; edit ayrı route. Finalized belge form inputlarıyla gösterilmez.

## Kullanıcı dili
Türkçe ve iş alanı odaklı. Internal state key, queue, outbox, idempotency, provider payload gibi teknik terimler normal kullanıcıya gösterilmez.

## Dead button yasağı
Görünür her aksiyon:
- gerçek route açmalı,
- gerçek modal/drawer çalıştırmalı,
- veya permission/availability nedeniyle açıkça disabled ve açıklamalı olmalıdır.

Sessiz no-op yasaktır.

## Belge satır grid'i
Ürün arama kod/barkod/QR/ad ile çalışır. Sonuçta kod, ad, fiyat, stok, rezerve, kullanılabilir gösterilir. Scanner Enter ile seçim ve quantity focus desteklenir.

## Rapor Merkezi
Hedef: yaklaşık 40 hazır rapor, 8 kategori:
- Cari & Finans
- Satış
- Alış
- Stok
- Üretim/Fason
- İthalat
- E-Ticaret/B2B
- Yönetim

Akış:
`Filtreler → KPI → Sonuç Tablosu → Kaydet → Zamanla → Excel/CSV → PDF/Yazdır`.

Generic visual report designer V1 kapsamı değildir.

## Yazdırma
- A4 portrait/landscape otomatik seçimi
- action column gizleme
- fit/wrap
- repeat table header
- firma/tarih/kayıt sayısı header
- footer/page no
- scrollbar/screenshot print yok

## Erişilebilirlik
Keyboard focus görünür, form label'ları gerçek, hata mesajları alanla ilişkili ve yalnız renge bağlı olmayan durum göstergeleri kullanılmalıdır.

## Responsive
Öncelik desktop operasyon ekranıdır; dar viewport'ta temel kullanım bozulmamalı, ancak masaüstü yoğun tablo verimliliği feda edilmez.
