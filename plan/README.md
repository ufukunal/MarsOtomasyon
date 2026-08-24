# MarsOtomasyon — Master Plan V3 — V16.3 Tasarım Uyumlu

Bu klasör `ufukunal/MarsOtomasyon` için otoriter geliştirme planıdır. `MarsEski` application code taşınmaz; yalnız iş kuralları, edge-case'ler ve migration referansıdır.

## Durum
- Plan: **V3 — V16.3 tasarım onaylı**
- UI referansı: **MarsOtomasyon V16.3 — Genel Tasarım Temizliği**
- PHP 8.5 + Laravel 13
- PostgreSQL 18 only
- PostgreSQL Full-Text Search + `pg_trgm`
- Valkey
- Laravel-native modular monolith
- Git: yalnız `main`
- Uygulama repo: `ufukunal/MarsOtomasyon`

## Ürün karakteri
MarsOtomasyon öncelikle **Türkçe, hızlı ve sade bir ön muhasebe/operasyon uygulamasıdır**. Kullanıcıya ERP mühendisliği, teknik queue/provider/state jargonları veya generic enterprise platform ekranları gösterilmez.

Ana navigasyon:
`Ana Sayfa → Cariler → Ürün/Stok → Satış → Alış → Üretim → Kasa/Banka → Çek/Senet → İadeler → İthalat → E-Ticaret/B2B → İletişim → Raporlar → Ayarlar`.

## Plan otoritesi
Çelişki halinde sıra:
1. `00_KARAR_KAYDI.md` V3 locked decisions.
2. İlgili business owner plan belgesi.
3. `26_V16_3_TASARIM_UYUMU.md` kullanıcı-visible ekran/akış sözleşmesi.
4. Test/DoD gate.
5. Migration/application davranışı.
6. Legacy code/eski belge.

## V3'te superseded edilen V2 varsayımları
- OpenItem/fatura bazlı settlement yok; **cari bakiye hareket defteri** var.
- Core lot/seri yok.
- Çoklu fiyat listesi yok; ürün başına tek satış + tek alış fiyatı.
- Üretim basit: `Reçete → Üretim Emri → Malzeme Çıkışı → Mamul Girişi → Tamamla`.
- Generic QCP/QMS/ECO/OEE/Shop Floor platformu core kapsam değil.
- Generic Visual Report/Document Designer yok; hazır Rapor Merkezi + versioned belge PDF şablonları var.
- İlk sürümde ayrı search servisi yok; PostgreSQL FTS + `pg_trgm` kullanılır.
- Canlı banka API/open-banking yok.

## Cari finans omurgası
- Cari authority: `account_transactions`.
- Satış faturası müşteri bakiyesini artırır, tahsilat azaltır.
- Alış faturası tedarikçi borcunu artırır, ödeme azaltır.
- Tahsilat/ödeme herhangi bir faturaya dağıtılmaz.
- Vade bilgi/raporlama alanıdır.
- Kullanıcı-visible bakiye: `Alacaklı` yeşil, `Borçlu` kırmızı, sıfır `Bakiye Yok`.

## Stok omurgası
- `stock_movements` authority.
- Reservation movement değildir.
- Sevk/irsaliye fiyat/KDV ekranı değildir.
- Depo transferi yalnız kaynak/hedef/miktar işidir.
- Stok sayımı sistem stoğu/sayılan/fark mantığıdır.

## Kasa/Banka
Ana menü yalnız:
`Tahsilat, Ödeme, Gider, Kasa Hareketleri, Banka Hareketleri, Virman, Ekstre İçe Aktar`.

Kasalar, Kasa Sayımı, Banka Hesapları ve Mutabakat ilgili hareket ekranlarından açılır.
Banka ekstresi CSV/Excel/MT940 ile alınır; kullanıcı `Dosya Seç → Önizleme → Eşleştirme → İçe Aktar` akışını görür.

## E-Ticaret / B2B
Tek E-Ticaret Integration Core + WooCommerce/Trendyol/B2B adapterları. Mars ürün/stok/fiyat/fatura authority'sidir. B2B kullanıcı hesabı önceden bir cariye bağlıdır; B2B indirimi ayrı değildir, **Cari İskontosu** kullanılır.

Kanal API bilgileri `E-Ticaret/B2B → Kanal Ayarları → Bağlantı`; SMS/E-Posta/WhatsApp/E-Belge `Ayarlar → Entegrasyonlar` altında tutulur. Secret değerleri kaydedildikten sonra maskelenir.

## UI uygulama kuralı
- Yeni belgeler ilgili listeden `Yeni` ile açılır.
- Detail ekranları readonly, edit ayrı route'tur.
- Generic placeholder form yok.
- Kullanıcı-visible dil Türkçe ve domain odaklıdır.
- V16.3'te görünen akışlar implementation acceptance baseline'ıdır.

## Delivery stratejisi
Her milestone çalışan dikey dilim üretir:
`schema → domain use-case → V16.3 uyumlu UI → invariant tests → observability`.

Future ihtiyaç için kullanılmayan interface/repository/table/framework önceden kurulmaz.
