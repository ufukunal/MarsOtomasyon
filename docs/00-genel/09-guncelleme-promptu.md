# MarsOtomasyon — Güncelleme Promptu

Bu dosyayı, **DEVIR-PROMPT.md'den sonra** ikinci mesaj olarak yapıştır.
Devir promptundaki bazı kararlar değişti; aşağıdakiler **geçerli olanlardır**
ve çelişki halinde bu dosya kazanır.

---

## 1. VERİTABANI MİMARİSİ DEĞİŞTİ — EN ÖNEMLİSİ

Devir promptunda "kartlar master'da" yazıyor. **Bu karar iptal edildi.**

### Geçerli yapı

```
MarsProject_Master     SADECE: companies, periods, users, roles,
                       permissions, company_user, exchange_rates,
                       app_settings, company_copy_permissions,
                       activity_log (master işlemleri)

ABCHolding_2026        HER ŞEY: cari ve ürün kartları, varyant, set,
ABCHolding_2027        konfigüratör, fiyat listeleri, lokasyonlar,
XYZltd_2026            görseller, kart ekleri, belgeler, stok hareketleri,
                       bakiye, maliyet, cari hareketler, kasa/banka,
                       çek/senet, numara serileri, ay kilitleri, sayımlar
```

### Sonuçları

**Kartlar da dönemdedir.** Devirde kartlar bir sonraki yıla **kopyalanır**.
Kart o yılın içinde donar: 2026'da ürün adını değiştirmek 2025 faturalarını
etkilemez.

**Global scope tamamen kalktı.** Dönem tablolarında `company_id` kolonu
**yoktur**. İzolasyon fizikseldir — farklı veritabanı. `MasterModel`
de global scope kullanmaz; master'daki tablolar zaten şirket üstüdür.

**Gerçek yabancı anahtar kullanılır.** Kartlar aynı veritabanında olduğu
için `documents.contact_id → contacts.id` kısıtı kurulur. Kart kodu/adı
belgeye yine kopyalanır ama bu kolaylık içindir, zorunluluk değil.

**Arşivlenen yıl kendi başına açılır.** Başka veritabanına ihtiyaç yok.

**`company_copy_permissions` kalktı.** Yerine master'da `company_copy_permissions`.
Kopyalama kaynak şirketin **aynı yıldaki** dönem veritabanından okur,
hedefin dönem veritabanına yazar; geçici ikinci bağlantı (`period_source`)
açılır.

### Devir kapsamı genişledi

```
1. KARTLAR KOPYALANIR (cari, ürün, varyant, set, konfigürasyon,
   fiyat listeleri, lokasyonlar, kart ekleri)
2. Stok açılışı — birim maliyet = kapanış hareketli ortalaması
3. product_costs taşınır
4. Cari bakiyeleri açılış fişi
5. Kasa, banka, vadesi gelmemiş çek/senet
6. Kaynak dönem kapanır
```

Taşınmayanlar: belgeler, hareketler, açık sipariş/teklif, taslaklar,
yolda transfer, karantinada bekleyen kalem.

### Depoda bu değişiklik UYGULANDI

`mars-repo.tar.gz` içindeki Faz 0, 1 ve 2 belgeleri yeni yapıya göre
güncellenmiştir. Devir promptundaki eski anlatımı değil, **depodaki
belgeleri** esas al.

---

## 2. KAPANAN KARARLAR

Devir promptunda "açık" görünen kararlar kapandı. **Yeniden sorma.**

| No | Karar |
|---|---|
| **A-001** | Pazaryeri stoğu **satış anında anlık** gönderilir; 15 dk tarama yedek mekanizma. Ürün bazında `channel_stock_mode`: `stock` / `production` / `manual`. Üretimden karşılanan ürün stok bitse de kanalda satışta kalır. |
| **A-002** | **Konfigüratör fiyatı etkilemez.** Yalnız ürün özelliklerini tanımlar (gövde, kristal, duy). Seçenekte fiyat alanı **yoktur**. Fiyat normal çözümleme sırasından gelir. |
| **A-003** | Reçetede fire yüzdesi **yok**; malzeme çıkışında elle girilir. |
| **A-004** | Konsolide rapor **ayrı bir izinle** (`reports.consolidated`) açılır; role bağlı değil. |
| **A-006** | Koli etiketi **ambar fişine** bağlıdır (irsaliyeye değil). "1/4" numarası ambar fişi kolilerinden üretilir. |
| **A-007** | Pazaryerlerine **varyantsız** gönderilir — her kart ayrı ürün. Varyant grubu yalnız B2B ve kendi sitelerinde kullanılır. |
| **A-008** | Kartlar **dönem veritabanında** (yukarıdaki madde 1). |
| — | **Kalite modülü kapsam dışı.** 8 ekran (kontrol planı, DÖF, 8D, kalibrasyon, SPC, tedarikçi kalitesi) menüden çıkarıldı, dokümante edilmeyecek. |
| — | **Satınalma talebi + teklif toplama eklendi** (basit). Faz 1'de `G-115`. Onay zinciri, bütçe kontrolü, RFQ e-postası **yok**. |

**Tek açık karar kaldı:** A-005 — etiket yazıcısı markası ve boyutları.
"Her marka her boyut" denildi; ZPL şablonu marka bağımsız yazılacak,
boyut ayardan gelecek. Faz 10'da netleşir.

---

## 3. YENİ İŞ KURALI BELGELERİ

Depoda şunlar eklendi, **okumadan Faz 3'e başlama**:

| Dosya | Konu |
|---|---|
| `02-is-kurallari/25-birim-donusumu.md` | **Stok hareketi her zaman temel birimde.** Belge satırı `base_quantity` + dondurulmuş `conversion_factor` saklar. Dönüşüm tanımlı değilse işlem engellenir. |
| `02-is-kurallari/26-fiyatlandirma.md` | Fiyat çözümleme: cari listesi → varsayılan liste → `products.list_price` → 0. Listeden %20+ sapmada uyarı. Maliyetin altında satışta uyarı (`cost.view` yoksa maliyetsiz metin). |
| `02-is-kurallari/27-kanal-stok-gonderimi.md` | Anlık gönderim, `channel_stock_mode`, set ürün sıfırlama, hata kuyruğu. |

`contacts` tablosuna `price_list_id` eklendi.

---

## 4. FAZ DURUMU

| Faz | Durum | Görev |
|---|---|---|
| 0 — Temel | **Yazıldı** | 21 görev (G-001…G-021) |
| 0b — Arayüz bileşenleri | **Yazıldı** | 3 görev |
| 1 — Kartlar | **Yazıldı** | 15 görev (G-101…G-115) |
| 2 — Stok | **Yazıldı** | 12 görev (G-201…G-212) |
| **3 — Satış** | **SIRADAKİ** | 12 görev planlandı |
| 4 — Alış | bekliyor | |
| 5 — Kasa/Banka | bekliyor | |
| 6 — İade | bekliyor | |
| 7 — İthalat | bekliyor | |
| 8 — Üretim ve fason | bekliyor | |
| 9 — E-ticaret | bekliyor | |
| 10 — Raporlar + belge tasarımcısı | bekliyor | |
| 11b — Dönem devri | kısmen yazıldı | G-1110, G-1112 var |
| 11 — Canlıya geçiş | bekliyor | |

---

## 5. PROTOTİP v64

Yanındaki HTML `marsotomasyon-PROTOTIP-ONAYLI-v64.html`.
v63'ten farkı: **kalite modülü menüden çıkarıldı** (16 grup, 161 madde).

Prototipte **düzeltilmeyen üç hata** var, bunlar bilinçli olarak
bırakıldı çünkü prototip kod tabanı değil şartname kaynağıdır.
**Yeni sistemde tekrarlanmamalı:**

1. Yeni Cari formunun "İletişim / Yetkililer" sekmesinde **başka bir
   carinin verisi** görünüyor (`CR0000036 · Avize Park · Sinan Öztaş`).
   Yeni kayıt formu **kendi boş verisiyle** açılmalı.
2. Yeni Ambar Fişi dolu açılıyor.
3. Yeni Cari'nin dört sekmesinden ikisi boş.

---

## 6. SENDEN İSTENEN

**Faz 3'ten başlayarak kalan fazların dokümantasyonunu üret.**
Bir seferde bir faz; bitince özetle, onay al, sonrakine geç.

Her faz için: veri modeli dosyaları, iş kuralları, kritik ekran
belgeleri, numaralı görev dosyaları.

Devir promptundaki **görev dosyası biçimi**, **halüsinasyona karşı
kurallar** ve **tutarlılık kontrol listesi** aynen geçerlidir.
Kontrol listesine şunları ekle:

- [ ] Dönem tablosunda `company_id` kolonu **yok** mu? (olmamalı)
- [ ] Kartlara **gerçek yabancı anahtar** kuruluyor mu?
- [ ] Devir kapsamına yeni kart tablosu eklenmesi gerekiyor mu?
- [ ] Konfigüratör seçeneğine **fiyat alanı eklenmemiş** mi?
- [ ] Stok hareketi **temel birimde** mi?
- [ ] Kanala stok gönderimi **anlık** mı tetikleniyor?
