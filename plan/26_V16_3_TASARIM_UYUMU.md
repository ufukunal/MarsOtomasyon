# 26 — V16.3 Tasarım Uyum Sözleşmesi

Bu belge, **MarsOtomasyon V16.3 — Genel Tasarım Temizliği** prototipinde onaylanan kullanıcı-visible kararları implementation sözleşmesine dönüştürür.

Bu dosya domain/business authority yerine geçmez; `00_KARAR_KAYDI.md` ve owner business rule dosyalarıyla birlikte okunur. Çelişki varsa locked business correctness korunur, fakat kullanıcı-visible davranış yeni onay olmadan V16.3'ten sapmaz.

## 1. Ana navigasyon
Sıra:
`Ana Sayfa → Cariler → Ürün/Stok → Satış → Alış → Üretim → Kasa/Banka → Çek/Senet → İadeler → İthalat → E-Ticaret/B2B → İletişim → Raporlar → Ayarlar`.

## 2. Genel ekran kuralları
- Yeni belge ilgili listeden açılır.
- Detail readonly'dir; edit ayrı route.
- Kesinleşmiş belge input görünümünde düzenlenemez.
- Generic placeholder detail/form yok.
- Kullanıcı-visible terimler Türkçedir.
- Teknik queue/outbox/idempotency/provider/fingerprint state normal kullanıcıya gösterilmez.
- Her görünür buton anlamlı route/action çalıştırır.

## 3. Workspace tabs
- `×` ile kapanır.
- Dirty değilse direkt kapanır.
- Dirty ise kaydetmeden kapat/vazgeç onayı gerekir.
- Dirty dot görünür.
- Save dirty state'i temizler.
- Readonly sekme direkt kapanır.
- Search typing tek başına dirty sayılmaz.

## 4. Cari
### Detail
Readonly bilgi merkezi. Aksiyonlar ihtiyaç bağlamına göre teklif/sipariş/fatura/tahsilat/ekstre/düzenle olabilir.

### Edit sekmeleri
Duplicate alanlar birleştirilmiştir:
- `Firma / Ticari`
- `İletişim / Yetkililer`
- `Sevk / Adres`
- `B2B / Bayi Erişimi`
- diğer gerçek cari alanları

### Firma / Ticari
Tek kaynak alanlar: resmi ünvan, vergi bilgileri, fatura/vergi uygulaması, vade, cari iskonto, risk limiti, para birimi.

### İletişim / Yetkililer
Firma iletişim kanalları + dinamik yetkili listesi. Aynı anda tek bir birincil yetkili.

### Sevk / Adres
Varsayılan Sevk Bilgileri içinde manuel Ambar/Nakliye:
- Firma Adı
- Şehir
- Şube
- Ambar Yetkilisi
- Ambar Telefonu
- Tercih
- Adres
- Not
- Alternatif Firma Ekle

Hazır carrier/warehouse company kataloğu yok.

### Bakiye
- Alacaklı: yeşil
- Borçlu: kırmızı
- Zero: Bakiye Yok

Fatura bazlı tahsilat kapatma görünümü yok.

## 5. Ürün
Product detail readonly; edit ayrı.

Detail gerçek domain sekmeleri taşıyabilir:
- Genel Bakış
- Stok Durumu/Hareketleri
- Satış/Alış
- En Çok Alanlar
- Fiyat
- Teknik Bilgi Dosyası
- Kurulum Kılavuzu
- Yıllık Performans
- Depolar
- Barkodlar
- Tedarikçiler
- Görseller
- Notlar/Dosyalar

Lot/seri UI yoktur.

## 6. Ürün araması
Belge satırında code/barcode/QR/name arama. Sonuçta code/name/price/stock/reserved/available görünür. Keyboard/scanner Enter desteklenir; seçim sonrası quantity focus.

## 7. Tek fiyat
Ürün başına tek satış ve tek alış fiyatı. Çoklu fiyat listesi ekranı yok.

## 8. KDV Sıfırla
Satış Siparişi ve Satış Faturası ürün satır alanında:
`Satır Ekle · Hızlı Ürün · KDV Sıfırla`.

KDV Sıfırla tüm line VAT rate değerini 0 yapar ve totals recalculation yapar.

## 9. Satış belgeleri
### Satış Siparişi
Sipariş/faturalanan/sevk/kalan miktar progress'i görünür.

### İrsaliye / Sevkiyat
Fiyat/KDV odaklı ekran değildir. Satırda sipariş miktarı, önceki sevk, bu sevk, kalan ve sevk/nakliye bilgisi odaktır.

### Satış Faturası
Fiyat/iskonto/KDV/toplam alanları vardır; kesinleşmiş detail readonly'dir.

## 10. Alış
### Satınalma Siparişi
Ordered/received/invoiced/remaining progress.

### Mal Kabul
Ürün/cari satış belgesi şablonu kullanılmaz. Satır:
- sipariş miktarı
- daha önce kabul
- bu kabul
- kalan
- `Uygun / Kontrol Bekliyor / Uygun Değil`

## 11. Depo Transferi
Cari/fiyat/KDV yok. Kaynak depo, hedef depo, ürün, miktar ve transfer progress'i vardır.

## 12. Stok Sayımı
Sistem miktarı, sayılan miktar ve fark. Ürün/cari satış belgesi alanları yok.

Quick Count barcode random scan + beep desteklenebilir.

## 13. Üretim
Basit üretim menüsü:
- Üretim Emirleri
- Reçeteler
- Malzeme Çıkışları
- Mamul Girişleri
- Fason Takibi
- Teknik Bilgi Dosyaları
- Üretim Raporu

Ana akış: `Reçete → Üretim Emri → Malzeme Çıkışı → Mamul Girişi → Tamamla`.

## 14. Fason
`Gönderilen Malzeme → Gelen Mamul → Fire/Eksik → Kalan → Tamamla`.

## 15. Kasa / Banka menüsü
Ana listede sadece:
- Tahsilat
- Ödeme
- Gider
- Kasa Hareketleri
- Banka Hareketleri
- Virman
- Ekstre İçe Aktar

Kasa Hareketleri içinden `Kasalar`, `Kasa Sayımı`.
Banka Hareketleri içinden `Banka Hesapları`, `Ekstre İçe Aktar`, `Mutabakat`.

## 16. Tahsilat / Ödeme
Ödeme tipi butonları:
- Nakit
- Banka Havale / EFT
- POS
- Sanal POS
- Çek
- Senet
- Diğer

Type-specific alanlar açılır. Cari mevcut bakiye, işlem tutarı ve işlem sonrası bakiye görünür.

### POS
Gross tahsilat cariyi azaltır; komisyon ayrı gider/banka/POS effect'tir.

### Çek
Çek no, vade, banka, şube, keşideci, yer, hesap/IBAN, portföy yeri ve ön/arka scan/upload.

### Senet
Senet no, vade, borçlu, düzenleme tarihi/yeri, kefil, portföy yeri, açıklama ve scan/upload.

## 17. Kasa Sayımı
- Kasa
- Sayım Tarihi
- Sayımı Yapan
- Sistem Bakiyesi
- Banknot/bozuk para adetleri
- Sayılan Toplam
- Sayım Farkı
- Fark Açıklaması
- Taslak Kaydet
- Sayımı Tamamla

Ürün/cari/KDV yok.

## 18. Banka Ekstresi
`Dosya Seç → Önizleme → Eşleştirme → İçe Aktar`.

Formatlar: Excel, CSV, MT940.

Tablo:
`Aktar | Tarih | Valör | Açıklama | Referans | Giriş | Çıkış | Eşleşme | Durum | İşlem`.

Durumlar:
- Eşleşti
- Eşleştirme Bekliyor
- Daha Önce Aktarıldı
- Aktarıldı

## 19. Çek / Senet
Received statuses:
`Portföyde, Bankada Tahsilde, Ciro Edildi, Tahsil Edildi, Karşılıksız, İade Edildi, İptal`.

Issued statuses:
`Hazırlandı, Teslim Edildi, Ödendi, Karşılıksız/Ödenmedi, İade Alındı, İptal`.

Physical location/history ve front/back images tutulur.

## 20. E-Ticaret / B2B menüsü
- Kanal Merkezi
- E-Ticaret Siparişleri
- Ürün Entegrasyonu
- E-Ticaret İadeleri
- Ürün / Sipariş Soruları
- Fatura Entegrasyonu
- Entegrasyon Sorunları
- Kanal Ayarları

## 21. Kanal Ayarları
Detail tabs:
`Bağlantı · Ürün · Sipariş · Fatura · Stok · Görsel`.

WooCommerce: Site URL, Consumer Key, Consumer Secret, status/test.
Trendyol: Supplier ID, API Key, API Secret, status/test.
Mars B2B dahili ise external secret yok.

Credential save sonrası secret maskelenir; gerçek değer tekrar gösterilmez.

## 22. Sistem Entegrasyonları
`Ayarlar → Entegrasyonlar`:
- E-Belge
- SMS
- WhatsApp
- E-Posta
- Scanner Agent
- E-Ticaret Kanalları yönlendirmesi

## 23. B2B
B2B account/user bir Mars carisine pre-bound. Siparişte cari seçilmez. Cari Edit B2B/Bayi Erişimi permissions/settings taşır. Cari Detail readonly B2B bilgisi gösterir.

B2B discount = Cari İskontosu.

## 24. Görseller
Upload sonrası image editor opsiyonel. Crop/rotate/flip/resize. Existing image `Resmi Düzenle` ile açılır.

Image destinations dinamik ve site/channel bazlıdır; her destination bağımsız main/gallery/order taşır.

## 25. Rapor Merkezi
40 hazır rapor hedefi, 8 kategori:
Cari & Finans, Satış, Alış, Stok, Üretim/Fason, İthalat, E-Ticaret/B2B, Yönetim.

Workspace:
`Filtreler → KPI → Sonuç Tablosu → Kaydet → Zamanla → Excel/CSV → PDF/Yazdır`.

Generic report designer yok.

## 26. Yazdırma
A4 portrait/landscape auto; action column gizleme; fit/wrap; repeat header; company/date/count header; footer/page no; scrollbar/screenshot print yok.

## 27. Search
V16.3 implementation ilk sürümde PostgreSQL FTS + `pg_trgm` kullanır. Ayrı search daemon yoktur.

## 28. Acceptance
Bir ekran V16.3 referansından implement edilirken:
- domain data gerçek,
- route/action gerçek,
- stale legacy renderer yok,
- teknik jargon yok,
- runtime error yok,
- user-visible duplicate sekme/alan yok
olmalıdır.
