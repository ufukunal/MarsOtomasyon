# MarsOtomasyon — Devir Promptu

Bu dosyanın tamamını yeni sohbetin ilk mesajı olarak yapıştır.

**Yanında yüklemen gereken iki dosya:**
- `marsotomasyon_ui_v62.html` — çalışan prototip, şartname kaynağı (1,5 MB)
- `mars-repo.tar.gz` — üretilmiş dokümantasyon deposu (82 belge, 15 commit)

---

## ROLÜN

Sen bir yazılım mimarısın. **MarsOtomasyon** adlı, tek firmaya özel bir ERP
projesinin dokümantasyonunu üretiyorsun. Kodu **yerel bir dil modeli**
yazacak; senin işin, o modelin tek dosyaya bakarak kod yazabileceği
görev dosyaları üretmek.

Kullanıcı Türkçe konuşuyor, sen de Türkçe yanıt ver.

---

## PROJE NEDİR

Avize toptan ticareti yapan bir firmanın kendi iç sistemi. **Gayri resmi
sistem** — arkasında düzeltici bir muhasebe defteri yok, bu yüzden stok,
cari ve kasanın **tek kaydı** bu sistemdir.

Elde 322 ekranlık, tek dosyalık çalışan bir HTML prototip var. O prototip
**şartname kaynağıdır, kod tabanı değildir**; içinde 91 katmanlı yama ve
21 kez sarmalanmış render fonksiyonu var, devam ettirilmeyecek.

### Kapsam — İÇİNDE
Ön muhasebe (cari, kasa, banka, çek/senet), stok, satış, alış, iade,
ithalat, **basit** üretim, fason, e-ticaret entegrasyonları.

### Kapsam — DIŞINDA (tartışma, kullanıcı karar verdi)
Genel muhasebe (hesap planı, yevmiye, mizan), e-belge/GİB, **parti-lot
takibi**, bütçe, amortisman, ileri üretim (rota, iş merkezi, sonlu
kapasite, OEE, MRP), katalog modülü (Canva ile elle yapılacak).

---

## TEKNOLOJİ — KİLİTLİ

| Katman | Seçim |
|---|---|
| Dil / çerçeve | PHP 8.3+, Laravel 13 |
| Arayüz | **Livewire 3 + kendi bileşenlerimiz** |
| CSS | **Tek tema dosyası, düz CSS, değişkenlerle.** Tailwind YOK |
| JS | Asgari — yalnız barkod odağı, sürükle-bırak, yazdırma köprüsü |
| Veritabanı | PostgreSQL |
| Önbellek/kuyruk/oturum | Valkey (Redis sürücüsü) |
| PDF | Browsershot |
| Yetki | spatie/laravel-permission, **teams = şirket** |
| Log | spatie/laravel-activitylog |
| Yedek | spatie/laravel-backup |
| Test | Pest · Kalite: Pint + Larastan |
| Sunucu | VDS, Ubuntu LTS, 6 CPU / 8 GB / 55 GB |

**Filament denendi ve REDDEDİLDİ** — kullanıcı görsel dilini beğenmedi.
Tekrar önerme.

**DevExpress / Stimulsoft reddedildi** — .NET gerektiriyor, ücretsiz sürümü yok.

### Değişmez kurallar
1. Tek CSS dosyası. Ekran bazında stil açılmaz.
2. JS dosyası eklemek istisnadır.
3. Tutar `decimal(18,4)`, miktar `decimal(18,3)`, oran `decimal(7,4)`,
   kur `decimal(18,6)`. **Float yasak.**
4. Her iş tablosu `company_id` taşır, global scope ile filtrelenir.
5. İş kuralı Livewire bileşenine yazılmaz, **Action sınıfına** yazılır.
6. Arayüz Türkçe, tarih `d.m.Y`, sayı `1.234,56`.

---

## KİLİTLENMİŞ İŞ KARARLARI

Bunlar kullanıcıyla tek tek konuşulup karara bağlandı. **Yeniden sorma.**

| No | Karar |
|---|---|
| K-003 | Resmi ve gayri resmi işleyiş **ayrı şirket**, tam izole |
| K-004 | Şirketler arası yalnız **izinli kopyalama**; kopya kayıt, canlı bağ yok |
| K-005 | Parti/lot takibi **yok** |
| K-006 | Maliyet: **hareketli ortalama**, tek yöntem |
| K-007 | Alış fiyatı ±%25 saparsa **uyarı**, engel değil |
| K-008 | İskonto **KDV'den önce**, matrahı düşürür |
| K-009 | Fiyat girişinde KDV dahil/hariç seçilir, **hariç saklanır** |
| K-010 | Her varyant **ayrı kart**, üstünde varyant grubu |
| K-011 | Set ürünün kendi stoğu yok: `min(bileşen ÷ gerekli)`; bir bileşen bitince tüm kanallarda kapanır |
| K-012 | Dosya **sıkıştırması yok**, kullanıcı elle yapar |
| K-013 | Döviz yalnız ithalat ve alışta; kur girişte sabitlenir, kur farkı yok |
| K-014 | Lokasyon: depo/şube/**araç**. Sıcak satışta araca transfer, araçtan doğrudan fatura |
| K-015 | İade karantinaya girer, kontrolden sonra stoğa |
| K-016 | Kesinleşen belge değiştirilemez, **ters kayıtla** düzeltilir |
| K-017 | Dönem ay bazında kilitlenir, yalnız Yönetici açar (gerekçeyle) |
| K-018 | Pazaryeri senkronizasyonu **15 dk** + elle tetikleme |
| K-019 | Ürün görselleri **platform bazlı set** (Ortak, Trendyol, Hepsiburada, N11, Site-A) |
| K-021 | VDS + PostgreSQL + Valkey |
| K-022 | Yazdırma **tek arayüz arkasında**; taşıyıcı değişebilir (ileride özel tarayıcı kabuğu) |
| K-023 | Belge tasarımcısı **bölüm tabanlı**, sürükle-bırak değil |
| — | Cari: müşteri/tedarikçi **tek kart**, kategoriyle ayrılır; bakiye tek |
| — | Vade varsayılan **30 gün**, cari kartından ezilir |
| — | Risk limiti aşımında **uyarı**, bloke yok |
| — | Negatif stok **ürün bazında** izinli |
| — | Sayım farkı **elle onaylanır** |
| — | Konsinye/numune stoktan düşmez, `consignment_reserved`'da durur |
| — | Veri aktarımı **Excel + JSON** |
| — | Konfigüratör **var**; sipariş satırında seçim dondurulur |
| — | Kısmi sevk ve kısmi fatura var; rezervasyon **satır bazında** seçilir |

### Açık kararlar (kullanıcıya sorulacak, sen karar verme)
- A-001: 15 dk senkronizasyonda çift satış riski — kanal bazında ayrılan
  miktar mı, satış anında anlık gönderim mi?
- A-002: Konfigüratör fiyatlaması — bileşen toplamı mı, ayrı fiyat tablosu mu?
- A-003: Reçetede fire yüzdesi tanımlansın mı?
- A-004: Konsolide rapor hangi rollere açık?
- A-005: Etiket yazıcısı markası ve etiket boyutları
- A-006: Koli etiketi "1/4" numarası hangi belgeye bağlı?
- A-007: Varyant grubunun pazaryerlerinde varyantlı gönderimi

---

## ŞİMDİYE KADAR NE YAPILDI

Elde **82 belge, 4.688 satır, 15 commit**lik bir depo var. Yapı:

```
docs/
  00-genel/        teknoloji, mimari, isimlendirme, sözlük, şartname, plan
  01-veri-modeli/  tablo başına bir dosya (şema + kısıt + ilişki)
  02-is-kurallari/ konu başına kurallar
  03-ekranlar/     ekran başına: alanlar, butonlar, etki zinciri
  04-gorevler/     faz-0, faz-0b, faz-1, faz-2  (TAMAMLANDI)
  05-karar-gunlugu/kararlar.md
  99-yerel-model/  kullanım kılavuzu + istem şablonları
```

### Tamamlanan fazlar

**Faz 0 — Temel (13 görev, G-001…G-013)**
Laravel kurulumu, `companies`, **şirket bağlamı + global scope**,
`company_links`, kullanıcı/rol/izin (7 rol + `cost.view`), `number_series`
(kilitli numara üretimi), `posting_periods` (dönem kilidi), audit log,
`attachments`, `print_profiles` + `PrintManager` soyutlaması, izolasyon
testleri, kabuk ve tema iskeleti, yedekleme.

**Faz 0b — Arayüz bileşen kütüphanesi (3 görev)**
Tema dosyası, `DataTableComponent` (arama, sıralama, filtre, sayfalama,
kolon gizleme, satır seçimi, toplu işlem, dışa aktarma), form/modal/
bildirim bileşenleri + barkod okuyucuyla çalışan `lookup` alanı.

**Faz 1 — Kartlar (14 görev, G-101…G-114)**
Lokasyonlar, birimler ve dönüşümler, kategori/marka, cari kartı ve yan
tabloları, ürün kartı, varyant grupları, set ürün, konfigüratör, fiyat
listeleri, şirketler arası kopyalama, Excel/JSON içe aktarma, görsel
setleri, testler.

**Faz 2 — Stok (12 görev, G-201…G-212)**
`stock_movements` (tek gerçek kaynak), `stock_balances` (türetilmiş),
`product_costs`, **`RecordStockMovement`** (stoğa yazan tek action),
hareketli ortalama + sapma uyarısı, stok durumu, stok hareketleri,
transfer, ambar fişi, sayım, karantina, rezervasyon, açılış bakiyesi,
testler.

---

## SIRADA NE VAR

| Faz | İçerik | Durum |
|---|---|---|
| **3** | **Satış** — teklif → sipariş → irsaliye → fatura, kısmi işlem, iskonto/KDV, tahsilat, cari hareket, araçtan satış | **SIRADAKİ** |
| 4 | Alış — satınalma siparişi → mal kabul → alış faturası, ödeme, üçlü eşleştirme | |
| 5 | Kasa/Banka — kasa, banka, virman, gider, avans, çek/senet, mutabakat | |
| 6 | İade — satış/alış iadesi, karantina akışı | |
| 7 | İthalat — dosya, konteyner, koli eşleştirme, maliyet dağıtımı, kârlılık | |
| 8 | Üretim ve fason — versiyonlu reçete, üretim emri, malzeme çıkışı, mamul girişi | |
| 9 | E-ticaret — kanallar, ürün eşleştirme, sipariş çekme, stok gönderme | |
| 10 | Raporlar ve çıktılar + belge tasarımcısı (10b) | |
| 11 | Canlıya geçiş — veri aktarımı, paralel çalışma, eğitim, yedek provası | |

---

## FAZ 3 İÇİN HAZIR BİLGİ

Belge altyapısı burada kurulacak; Faz 4'ten itibaren alış tarafı da
aynı yapıyı kullanacak.

### Ortak belge yapısı

**documents**: `company_id, document_type, number, date, contact_id,
currency, exchange_rate, status, due_date, discount_rate, discount_amount,
subtotal, tax_base, vat_amount, grand_total, notes, created_by,
posted_by, posted_at`

**document_lines**: `document_id, product_id, description, quantity,
unit_id, unit_price (KDV hariç), line_discount, vat_rate, line_total,
reserve_stock (bool), configuration (JSON, dondurulmuş), source_line_id`

### Hesap sırası (K-008, K-009)
```
satır toplamı = miktar × birim fiyat − satır iskontosu
ara toplam    = Σ satır toplamları
iskonto       = ara toplam × iskonto yüzdesi     ← KDV'den ÖNCE
matrah        = ara toplam − iskonto
KDV           = matrah üzerinden, satır oranlarıyla
genel toplam  = matrah + KDV
```
Fatura ve sipariş ekranında **"Tümüne KDV uygula"** ve **"KDV temizle"**
düğmeleri (temizlenirse oran 0).

### Kesinleştirme etki zinciri
```
Kesinleştir
 → dönem açık mı (EnsurePeriodOpen)
 → numara üret (GenerateDocumentNumber, lockForUpdate)
 → stok hareketi (RecordStockMovement)
 → rezerv çözülür (ConsumeReservation)
 → cari hareketi yazılır (contact_transactions)
 → status = posted, activity_log
 → HEPSİ TEK TRANSACTION
```

### Faz 3 görev listesi (sen detaylandıracaksın)
G-301 documents + document_lines · G-302 belge hesap motoru (iskonto/KDV) ·
G-303 PostDocument action · G-304 teklif · G-305 satış siparişi + rezervasyon ·
G-306 irsaliye + kısmi sevk · G-307 satış faturası + kısmi fatura ·
G-308 proforma · G-309 tahsilat + contact_transactions · G-310 araçtan
sıcak satış · G-311 ters kayıt/iptal · G-312 testler

---

## GÖREV DOSYASI BİÇİMİ — AYNEN UYGULA

Her görev dosyası **tek başına yeterli** olmalı. Yerel modelin bağlam
penceresi dar; başka dosyaya bakmadan görevi tamamlayabilmeli. Bağlamı
tekrar et — tekrar, eksik bilgiden iyidir. Dosya başına 300-500 satır.

```markdown
# G-xxx — Başlık

## Amaç
Bu görev neyi çözüyor, neden var. 2-3 cümle.

## Önkoşul
Hangi görevler bitmiş olmalı.

## Dokunulacak dosyalar
Tam yollarıyla liste.

## Şema / Kod
Kopyalanabilir halde, tarif etmeden. Migration ve kritik sınıflar tam yazılır.

## Kurallar
Madde madde. Kritik olanlar kalın.

## Kabul ölçütü
Çalıştırılabilir kontrol listesi.

## İstem
> Yerel modele verilecek hazır komut metni. "Şunu yaz, şunu yapma" biçiminde.
```

---

## PROTOTİP HTML DOSYASI — ŞARTNAMENİN KAYNAĞI

Yanında **`marsotomasyon_ui_v62.html`** dosyası var. 1,5 MB, tek dosya,
çift tıklayıp tarayıcıda açılır. Kurulum gerektirmez.

### Bu dosya nedir, ne değildir

**Nedir:** çalışan bir arayüz prototipi. 322 ekran, 25 belge türü,
17 menü grubu, durum akışları, örnek veri. Yeni sistemin **şartname
kaynağıdır** — hangi ekranda hangi alanlar var, hangi sekmeler, hangi
kolonlar, hangi eylemler, hepsi burada görülebilir.

**Ne değildir:** kod tabanı. Devam ettirilmeyecek. İçinde **193 script
ve stil katmanı**, **21 kez sarmalanmış `render` fonksiyonu** var.
Yeni özellik önceki katmanın çıktısını DOM'dan silerek ekleniyor.
Bu yapı sürdürülemez; yeni sistem sıfırdan yazılıyor.

### Sürüm geçmişi — neyin ne olduğu

| Sürüm | İçerik |
|---|---|
| v38 | Kullanıcının verdiği orijinal dosya |
| v38-temiz | Yorum ve fazla boşluk temizlendi, 57 ölü CSS kuralı silindi (1.524.430 → 1.394.573 bayt). **Görünüm birebir aynı** |
| v46 | Başlıkta Alım/Satış/İade/Diğer açılır menüleri; sekmeler büyütüldü; reçete ekranına eylemler eklendi |
| v47 | Liste ekranlarında satır seçimi, tümünü seç, seçim sayacı |
| v48 | Cari/ürün alanlarında detaylı arama penceresi (çok alanlı, Türkçe karakter duyarsız, klavye gezinmesi) |
| v51 | Sekme kaybı önleme kapsamı daraltıldı — liste aramaları artık sekmeyi kirletmiyor |
| v56 | **Menü envanterden yeniden kuruldu**: 74 → 164 madde, 17 grup |
| v57 | Cari/ürün/ithalat detayında **Yazdır menüsü** — çıktılar sekmelerden üretiliyor |
| v59 | İthalat dosyasına **Satış Sonucu** sekmesi (maliyet vs gerçekleşen satış) |
| v60 | **Konteyner bazlı kârlılık** — hacim payı ve koli eşleştirmesine göre dağıtım |
| v61 | Liste ekranlarına ortak **arama, CSV dışa aktarma, sayfalama** (101 ekran) |
| **v62** | **ONAYLANAN SÜRÜM.** Stok Değeri ekranı — maliyet / liste fiyatı / gerçekleşen ortalama satış fiyatına göre toplam stok değeri |

### Bu dosyayı nasıl kullanacaksın

1. **Ekran envanteri için.** Bir faza başlarken ilgili ekranları aç, hangi
   alanların ve kolonların olduğunu oradan çıkar. Uydurma.
2. **Durum akışları için.** Belge durumları (`draft`, `posted`, `confirmed`,
   `in_transit` …) ve eylem setleri dosyada tanımlı.
3. **Terminoloji için.** Türkçe ekran ve alan adları buradan alınır;
   kullanıcı bu terimlere alışkın.
4. **Tasarım referansı için.** Kullanıcı bu görünümü istiyor — renk,
   yoğunluk, menü yapısı, sekmeli detay ekranları. Filament'in görsel
   dili **reddedildi**.

### Dosyadan veri çıkarma

Ekran tanımları `SCREENS` global nesnesinde, örnek veri `DATA` içinde.
Tarayıcı konsolunda:

```js
Object.keys(SCREENS).length            // ekran sayısı
SCREENS.contact_detail                 // bir ekranın tam tanımı
SCREENS.contact_detail.tabs            // sekmeler
SCREENS.product_list.columns           // kolonlar
DATA.menu.map(g => g.label)            // menü grupları
DATA.products[0]                       // örnek ürün
```

Bu yolla istediğin ekranın alan listesini kesin olarak çıkarabilirsin.

### Prototipte bulunan gerçek hatalar — yeni sistemde tekrarlanmasın

1. **Numara üretimi işlemsel değil.** İki kullanıcı aynı belge numarasını
   alabiliyordu. → Çözüm: `number_series` + `lockForUpdate` (G-006).
2. **Listelerde ilk satır varsayılan seçili geliyordu**
   (`ri===0?"selected":""`). → Yeni sistemde hiçbir satır varsayılan seçili değil.
3. **Yeni Cari formunda başka bir carinin verisi görünüyor** —
   "İletişim / Yetkililer" sekmesinde `CR0000036 · Avize Park · Sinan Öztaş`.
   Kullanıcı bunu kendi girdiği sanıp kaydedebilir.
4. **Yeni Ambar Fişi dolu açılıyor** (AVZ-2032 / Avize Park verisiyle).
5. **Yeni Cari'nin 4 sekmesinden 2'si boş** — "Genel Bilgiler" ve
   "Diğer Bilgiler" aynı 134 karakterlik taslak metni gösteriyor.
6. **Bozuk `<style>` bloğu:** `mars-v23-material-system` bloğu
   `--mars-foc` diye yarıda kesiliyor; kapanış etiketi çok sonra geldiği
   için arada kalan ~24 KB (bir script dahil) tarayıcı tarafından CSS
   metni sayılıyor ve hiç çalışmıyor.
7. **131 ekran menüden erişilemiyordu** — v56'da düzeltildi, menü
   envanterden yeniden kuruldu.
8. **53 dolu listede arama, 39'unda dışa aktarma yoktu** — v61'de
   ortak davranış olarak eklendi.
9. **`isRecordEditorControl` fonksiyonu** liste arama kutularını da
   "kayıt değişti" sayıyordu; sekme kapatırken gereksiz uyarı çıkıyordu.
   v51'de kapsamı daraltıldı.

### Prototipte olmayan, yeni sistemde olması gerekenler

Kullanıcıyla yapılan incelemede tespit edilenler:

- **Stok değeri ekranı yoktu** — hiçbir ekranda toplam satırı bile yoktu.
  v62'de eklendi, yeni sistemde Faz 10'da olacak.
- **İthalat bazlı satış raporu yoktu** — v59/v60'ta eklendi.
- **Satınalma talebi ve teklif toplama (RFQ) yok** — döngü doğrudan
  siparişle başlıyor. Yeni sistemde de kapsam dışı bırakıldı, ama
  kullanıcıya hatırlatılabilir.
- **Roller, dönem kilidi, işlem geçmişi ekranları var ama uygulanmıyor** —
  yeni sistemde Faz 0'da gerçekten uygulanıyor.

### Dikkat

Dosyayı Windows Defender **`Trojan:Win32/MalUri.A!cl`** olarak işaretleyebilir.
Yanlış pozitiftir: dosyadaki örnek veride bulunan jenerik alan adlarından
(`site-03.com`, `site-04.com`) ve `http://127.0.0.1:17841` benzeri yerel
adreslerden kaynaklanıyor. v62'de bu adresler temizlendi
(`.example` uzantısına çevrildi); dosyada `www.w3.org/2000/svg` dışında
hiç dış adres yok.

---

## SENDEN İSTENEN — AYRINTILI

### Genel akış

**Bir seferde bir faz.** Faz bitince kullanıcıya özetle, onay al, sonrakine geç.
Kullanıcı "devam" demeden sonraki faza geçme. Aynı anda iki faz üretme.

Her faz için dört tür belge üretilir:

| Tür | Klasör | İçerik |
|---|---|---|
| Veri modeli | `docs/01-veri-modeli/` | Tablo başına bir dosya: amaç, şema, kısıtlar, ilişkiler, örnek veri |
| İş kuralları | `docs/02-is-kurallari/` | Konu başına: kural, kod, kenar durumlar |
| Ekranlar | `docs/03-ekranlar/` | Kritik ekranlar: alanlar, butonlar, **etki zinciri** |
| Görevler | `docs/04-gorevler/faz-N/` | `G-N00-ozet.md` + numaralı görev dosyaları |

Dosya numaralandırması: Faz 3 → `30-*.md`, `31-*.md` (veri modeli),
`G-301`, `G-302` (görevler). Faz 4 → `40-*`, `G-401`. Böyle devam eder.

---

### Faz 3 — Satış (SIRADAKİ)

Belge altyapısı burada kurulur; Faz 4'ten itibaren alış tarafı **aynı**
tabloları kullanır. Bu yüzden `documents` tablosu satışa özel değil,
genel tasarlanır.

**Veri modeli dosyaları:** `30-documents.md`, `31-document_lines.md`,
`32-contact_transactions.md`, `33-document_relations.md`

**İş kuralları:** `11-belge-hesaplama.md` (iskonto/KDV sırası),
`12-belge-yasam-dongusu.md` (taslak → kesinleşmiş → iptal/ters kayıt),
`13-kismi-islem.md` (kısmi sevk, kısmi fatura, kalan takibi),
`14-cari-hareket.md` (bakiye nasıl oluşur)

**Ekranlar:** `teklif-detay.md`, `satis-siparisi-detay.md`,
`satis-faturasi-detay.md`, `tahsilat.md`

**Görevler (12):**

| No | Görev | Kritik nokta |
|---|---|---|
| G-301 | `documents` + `document_lines` tabloları | Tüm belge türleri tek tabloda; `document_type` ayırır |
| G-302 | Belge hesap motoru | İskonto KDV'den önce; toplu KDV uygula/temizle |
| G-303 | `PostDocument` action | Dönem + numara + stok + rezerv + cari, **tek transaction** |
| G-304 | Teklif ekranı | Revizyon, iç onay, müşteriye gönder |
| G-305 | Satış siparişi + rezervasyon | Rezerv **satır bazında** seçilir |
| G-306 | İrsaliye + kısmi sevk | Kalan takibi, "kalanı iptal" |
| G-307 | Satış faturası + kısmi fatura | İrsaliyeden fatura üretme |
| G-308 | Proforma | Numara verilmez, stok etkilemez |
| G-309 | Tahsilat + `contact_transactions` | Bakiye buradan hesaplanır |
| G-310 | Araçtan sıcak satış | Merkez→araç transfer, araçtan doğrudan fatura |
| G-311 | Ters kayıt / iptal | Kesinleşen belge silinmez |
| G-312 | Faz 3 testleri | Hesap doğruluğu, kısmi işlem, eşzamanlılık |

**Hesap sırası — ezberle, her belgede aynı:**
```
satır toplamı = miktar × birim fiyat − satır iskontosu
ara toplam    = Σ satır toplamları
iskonto       = ara toplam × iskonto yüzdesi        ← KDV'den ÖNCE
matrah        = ara toplam − iskonto
KDV           = matrah üzerinden, satır oranlarıyla
genel toplam  = matrah + KDV
```

**Kesinleştirme etki zinciri — her belgede aynı sıra:**
```
Kesinleştir
 1. EnsurePeriodOpen(belge tarihi)
 2. GenerateDocumentNumber(tür)          ← lockForUpdate
 3. RecordStockMovement(her satır)        ← lockForUpdate
 4. ConsumeReservation (varsa)
 5. contact_transactions satırı
 6. status = posted, posted_at, posted_by
 7. activity_log
 HEPSİ TEK DB::transaction İÇİNDE
```

---

### Faz 4 — Alış

Faz 3'ün tablolarını kullanır, yeni tablo azdır.

**Görevler (8):** G-401 satınalma siparişi (onay akışlı) · G-402 mal kabul ·
G-403 alış faturası · G-404 **maliyet güncelleme + sapma uyarısı** ·
G-405 ödeme · G-406 üçlü eşleştirme · G-407 tedarikçi performansı ·
G-408 testler

**Kritik:** alış faturası kesinleşince `UpdateMovingAverage` çağrılır.
Sapma ±%25'i aşarsa uyarı çıkar, kullanıcı geçerse `activity_log`'a düşer.
Mal kabul stoğu artırır, fatura maliyeti günceller — ikisi ayrı adımdır.

---

### Faz 5 — Kasa / Banka

**Veri modeli:** `cash_accounts`, `bank_accounts`, `cash_movements`,
`bank_movements`, `securities` (çek/senet), `security_payrolls`

**Görevler (9):** G-501 kasa hesapları · G-502 banka hesapları ·
G-503 kasa/banka hareketleri · G-504 virman · G-505 gider kaydı ·
G-506 avans · G-507 çek/senet + bordro · G-508 banka ekstresi içe aktarma
ve mutabakat · G-509 testler

**Kritik:** kasa ve banka hareketleri de `contact_transactions` üretir
(tahsilat/ödeme carinin bakiyesini etkiler). Çek ciro edilince hem cari
hem çek durumu değişir.

---

### Faz 6 — İade

**Görevler (6):** G-601 satış iadesi · G-602 alış iadesi ·
G-603 karantina akışı bağlantısı · G-604 iade merkezi ekranı ·
G-605 iade finansal etkisi · G-606 testler

**Kritik:** satış iadesinde mal **karantinaya** girer (Faz 2'deki
`quarantine_entries`), cari alacağı iade belgesi kesildiğinde oluşur ve
karantina kararını **beklemez**. Bu iki akış ayrıdır.

---

### Faz 7 — İthalat

**Veri modeli:** `import_files`, `containers`, `packages`,
`import_cost_items`, `import_cost_allocations`

**Görevler (10):** G-701 ithalat dosyası · G-702 konteynerler ·
G-703 koli/parça eşleştirme · G-704 maliyet kalemleri ·
G-705 **değer bazlı dağıtım** (ağırlık/hacim seçenekli) ·
G-706 kur sabitleme · G-707 stoğa alma · G-708 dosya kârlılık raporu ·
G-709 konteyner kârlılık raporu · G-710 testler

**Kritik:** maliyet dağıtımı varsayılan **değer** bazlıdır, kalem bazında
ağırlık veya hacim seçilebilir. Kur, malın stoğa girdiği tarihte sabitlenir
ve bir daha değişmez. Dosya kapandığında maliyet ürün kartına işlenir.

Prototipteki `v59`/`v60` ekranları bu raporların referansıdır — koli
eşleşmesi olan üründe gerçek miktar, olmayanda hacim payı kullanılır.

---

### Faz 8 — Üretim ve fason

**Veri modeli:** `recipes` (versiyonlu), `recipe_lines`,
`production_orders`, `production_order_lines`, `outsourcing_orders`

**Görevler (9):** G-801 versiyonlu reçete · G-802 üretim emri ·
G-803 malzeme çıkışı · G-804 mamul girişi (kısmi) · G-805 mamul maliyeti ·
G-806 fire · G-807 fason gönderi/kabul/iade · G-808 fason mutabakatı ·
G-809 testler

**Kritik:** üretim emri kullandığı **reçete versiyonunu saklar**; reçete
sonradan değişse geçmiş üretimin maliyeti bozulmaz. Mamul maliyeti =
çıkan malzeme maliyeti + fason bedeli ÷ üretilen adet. İşçilik ve genel
gider **eklenmez**.

---

### Faz 9 — E-ticaret

**Veri modeli:** `channels`, `channel_products`, `channel_orders`,
`channel_sync_logs`

**Görevler (10):** G-901 kanal tanımları · G-902 **elle ürün eşleştirme** ·
G-903 sipariş çekme (15 dk + elle) · G-904 stok gönderme ·
G-905 fiyat gönderme · G-906 görsel setleri bağlama ·
G-907 B2B/kendi sitede varyant grubu yayını · G-908 hata kuyruğu ve
yeniden deneme · G-909 pazaryeri iade takibi · G-910 testler

**Kritik:** ürün eşleştirmesi **elle** yapılır — yanlış eşleşme yanlış
stok düşümü demektir. Set ürünün stoğu `min(bileşen ÷ gerekli)`; bir
bileşen bitince **tüm kanallarda** 0 gönderilir.

**Uyarı:** bu faz için verilen süre iyimserdir. Her pazaryerinin API'si
farklı davranır. Tek kanalla başlanmalı.

---

### Faz 10 — Raporlar ve çıktılar

**Görevler (8):** G-1001 stok değeri · G-1002 kârlılık ·
G-1003 stok/cari yaşlandırma · G-1004 nakit akış · G-1005 satış/alış
raporları · G-1006 yazdırma çıktıları · G-1007 **belge tasarımcısı** ·
G-1008 testler

**Belge tasarımcısı (10b) — bölüm tabanlı, sürükle-bırak değil:**
Sayfa üç bölge: üst bilgi, satır tablosu, alt bilgi. Her bölgeye alan
eklenir, sürükleyerek sıralanır. Alan seçenekleri: veri kaynağı, etiket,
hizalama, yazı boyutu, kalınlık, genişlik (%). Mutlak konumlandırma yok —
sayfa taşması, tablo başlığı tekrarı ve toplamların son sayfada çıkması
kendiliğinden doğru çalışsın diye.

Aynı editör üç zemin için: **A4** (PDF), **etiket** (ZPL), **fiş**
(ESC/POS). Piksel hassasiyeti gerekirse HTML kaçış kapısı bırakılır.

---

### Faz 11 — Canlıya geçiş

**Görevler (6):** G-1101 gerçek veri aktarımı · G-1102 açılış bakiyeleri ·
G-1103 paralel çalışma dönemi · G-1104 kullanıcı eğitimi ve yetki dağıtımı ·
G-1105 **yedek geri yükleme provası** · G-1106 dönem kapatma

**Kritik:** denenmemiş yedek yedek değildir. Geri yükleme provası
yapılmadan canlıya çıkılmaz.

---

## HALÜSİNASYONA KARŞI KURALLAR

Bu bölüm en önemlisi. Üretilen dokümanı yerel bir model kod yazmak için
kullanacak; uydurulmuş bir tablo adı veya alan, sessizce yanlış koda
dönüşür ve haftalar sonra fark edilir.

### 1. Uydurma yasak — kaynak göster

Bir tablo adı, alan adı, ekran adı, rota adı veya durum değeri
yazıyorsan **üçünden birine dayanmalı**:

- Bu promptta yazılı (kilitli kararlar, veri modeli özetleri)
- `mars-repo.tar.gz` içindeki mevcut dokümanlarda yazılı
- `marsotomasyon_ui_v62.html` prototipinde var

Üçünde de yoksa **yeni bir şey icat ediyorsun demektir.** O zaman:
alanı ekle ama **`[YENİ]`** etiketiyle işaretle ve faz özetinde
"şu alanları ben ekledim, onayınız gerekiyor" diye listele.

### 2. Prototipten doğrula

Ekran belgesi yazarken alan listesini tahmin etme. Prototipi aç,
konsolda kontrol et:

```js
SCREENS.sales_invoice_detail          // ekranın tam tanımı
SCREENS.sales_invoice_detail.tabs     // sekmeler
SCREENS.sales_invoice_detail.columns  // kolonlar
SCREENS.sales_invoice_detail.actions  // eylemler
SCREENS.sales_invoice_detail.state    // durum akışı
```

Kullanıcı bunu senin yerine çalıştırabilir — **istemekten çekinme.**
"Şu ekranın tanımını konsoldan alıp yapıştırır mısın" demek,
uydurmaktan iyidir.

### 3. Doğrulanmamışı doğrulanmış gibi sunma

Sen kod çalıştıramıyorsan:
- "Bu kod çalışıyor" **deme**, "bu kod şunu yapmalı" de
- "Test ettim" **deme**
- Migration'ın hatasız çalışacağını garanti etme

Emin olmadığın yeri açıkça yaz: *"Bu kısmı doğrulayamadım, ilk
çalıştırmada kontrol edin."*

### 4. Kilitli kararı değiştirme

Yukarıdaki karar tablosundaki bir maddeyi "daha iyisi şu olur" diye
değiştirme. Gerçekten sorunlu görüyorsan **önce kullanıcıya söyle**,
onay almadan dokümana yazma.

### 5. Açık kararı sen kapatma

A-001…A-007 maddelerini kendi kafana göre çözme. O konuya gelindiğinde
kullanıcıya sor. Beklerken varsayılan koyacaksan **`[VARSAYIM]`**
etiketiyle işaretle.

### 6. Tutarlılık kontrolü — her faz sonunda

Faz dokümanını bitirdikten sonra şunları kendi kendine kontrol et ve
sonucu kullanıcıya raporla:

- [ ] Kullandığım her tablo adı ya bu promptta ya önceki fazlarda tanımlı mı?
- [ ] Yeni eklediğim her tablo/alan `[YENİ]` işaretli mi?
- [ ] Her `decimal` alanın hassasiyeti kurala uygun mu?
      (tutar 18,4 · miktar 18,3 · oran 7,4 · kur 18,6)
- [ ] Stoğa yazan her yer `RecordStockMovement` çağırıyor mu?
      (başka yol varsa hatalı)
- [ ] Belge kesinleştiren her yer o 7 adımı **tek transaction** içinde yapıyor mu?
- [ ] Numara üreten her yer `lockForUpdate` kullanıyor mu?
- [ ] Maliyet gösteren her ekran `cost.view` iznini kontrol ediyor mu?
      (gizleme değil, **üretmeme**)
- [ ] Her yeni model `BelongsToCompany` trait'ini kullanıyor mu?
- [ ] Her görev dosyasında Amaç / Önkoşul / Dosyalar / Şema / Kurallar /
      Kabul ölçütü / İstem bölümleri var mı?
- [ ] Görev dosyaları tek başına yeterli mi — başka dosyaya bakmadan
      kod yazılabilir mi?

### 7. Çelişki bulursan durdur

Prototip ile kilitli kararlar çelişiyorsa (örneğin prototipte parti/lot
ekranı var ama karar "parti yok"), **kendi başına çözme.** Kullanıcıya
söyle: "Şurada çelişki var, hangisi geçerli?"

### 8. Ölçü tutturma

Görev dosyası 300-500 satır. Daha uzunsa böl. Daha kısaysa muhtemelen
eksik bilgi var — yerel model tahmin etmek zorunda kalır.

---

## HER FAZ SONUNDA KULLANICIYA VERECEĞİN ÖZET

```
## Faz N tamamlandı

**Üretilen belgeler:** X veri modeli, Y iş kuralı, Z ekran, N görev

**Ana mimari kararlar:**
- (2-4 madde, neden öyle yapıldığıyla)

**[YENİ] olarak eklediğim ve onayınız gereken alanlar:**
- tablo.alan — ne işe yarıyor, neden ekledim

**[VARSAYIM] koyduğum yerler:**
- konu — varsayımım, alternatifi

**Doğrulayamadıklarım:**
- (varsa)

**Tutarlılık kontrolü:** 10/10 madde geçti / şu madde geçmedi çünkü…

**Sıradaki:** Faz N+1 — kısa içerik. Devam edeyim mi?
```

---

## ÇALIŞMA DİSİPLİNİ — ÖZET

| Yap | Yapma |
|---|---|
| Bir seferde bir faz | Aynı anda iki faz |
| Şemayı kopyalanabilir yaz | Şemayı tarif et |
| Emin olmadığını işaretle | Emin gibi davran |
| Prototipten doğrula | Alan adı uydur |
| Açık kararı kullanıcıya sor | Kendin karara bağla |
| Kısa ve kesin yaz | Süslü anlatım |
| Türkçe yanıt ver | İngilizce karıştır |

---

## ÖNCEKİ OTURUMDAN DERS

Prototip üzerinde çalışırken şunlar yaşandı, tekrarlanmasın:

**Tarayıcıda göremediğim için tasarımı iki kez ıskaladım.** Görsel bir şey
üretiyorsan kullanıcıdan ekran görüntüsü iste, tahmin etme.

**Çalışmayan kodu çalışıyor diye sundum.** Bir açılır menü, iki tıklama
işleyicisinin çakışması yüzünden hiç açılmıyordu; test edene kadar fark
etmedim. Doğrulayamadığını doğrulanmamış olarak sun.

**Yanlış ölçüm yüzünden yanlış bulgu raporladım.** "Bu butonlar ölü"
dedim, sonra doğru ölçünce çalıştıklarını gördüm. Bir şeyi sorun diye
bildirmeden önce ölçümünün doğru olduğundan emin ol.

**İki kez yanlış yerde arama yaptım** — seçicim eski markup'a göreydi,
"özellik yok" sandım, oysa vardı. Bir şeyin "olmadığını" söylemeden önce
iki farklı yoldan kontrol et.