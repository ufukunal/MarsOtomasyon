# MarsOtomasyon — Şartname v1

Bu belge, mevcut tek dosyalık HTML prototipin (322 ekran) yerine geçecek gerçek
sistemin veri modelini ve iş kurallarını tanımlar. Prototip bundan sonra
şartname kaynağı olarak kullanılır, kod tabanı olarak değil.

**Kapsam:** ön muhasebe, stok, satış, alış, iade, ithalat, basit üretim, fason,
e-ticaret entegrasyonları.
**Kapsam dışı:** genel muhasebe (hesap planı, yevmiye, mizan), e-belge/GİB,
parti-lot takibi, bütçe, amortisman, ileri üretim (rota, iş merkezi, sonlu
kapasite, OEE, MRP).

**Teknik zemin:** Laravel + Filament, PostgreSQL (paylaşımlı hostingte
MySQL/MariaDB + InnoDB), Valkey (önbellek, kuyruk, oturum) — VPS varsa.

---

## 1. Şirket yapısı

Sistem çok şirketlidir ve şirketler **tam izoledir**. Resmi ve gayri resmi
işleyiş ayrı birer şirket olarak kurulur.

Her şirketin kendine ait: stoğu, cari kartları, kasa ve bankaları, belge
serileri, fiyat listeleri, kullanıcı yetkileri, raporları.

Şirketler arası tek bağ **izinli veri kopyalamadır**:

- İzin, şirket çifti ve veri türü (stok kartı / cari kart) bazında tanımlanır.
- İzin yoksa diğer şirketin verisi hiçbir ekranda görünmez, aranamaz.
- Kopyalama tek tıkla yapılır; hedefte **yeni kayıt** oluşur, canlı bağ kurulmaz.
- Kopyalanan kayıtta `kaynak_sirket_id` ve `kaynak_kayit_id` saklanır; "kaynakta
  değişmiş mi" kontrolü ayrı bir işlemle yapılır, otomatik güncelleme yoktur.
- Dış bir firmaya kurulum verildiğinde o şirketin izni yoktur, diğerlerini göremez.

Kullanıcı üst çubuktan aktif şirketi değiştirir. Konsolide rapor yalnızca
yetkisi olan kullanıcıya, açıkça "konsolide" seçildiğinde gösterilir.

---

## 2. Veri modeli

### 2.1 Temel

**companies** — id, ad, unvan, vergi dairesi/no, adres, logo, varsayılan_vade_gun (30),
maliyet_sapma_esigi (%25), aktif

**company_links** — kaynak_sirket_id, hedef_sirket_id, tur (stok | cari), aktif
Kopyalama izni. Kayıt yoksa izin yok.

**users** — id, ad, e-posta, parola, aktif
**roles** — Yönetici, Muhasebe, Satış, Satınalma, Depo, Üretim, Görüntüleyici
**permissions** — ekran bazında gör / ekle / değiştir / iptal, ayrıca bağımsız
`maliyet_gor` izni (satış personeli maliyet ve kâr görmez)
**company_user** — hangi kullanıcı hangi şirkette hangi rolde

**posting_periods** — sirket_id, yil, ay, durum (açık | kapalı), kapatan, kapanis_tarihi
Kapalı döneme kayıt girilemez, geçmiş belge değiştirilemez. Yalnızca Yönetici
geçici açar, açma işlemi loglanır.

**number_series** — sirket_id, belge_turu, on_ek, yil, son_numara
Numara kayıt anında veritabanı işlemiyle üretilir (`SELECT … FOR UPDATE`).
Boşluk ve çakışma olmaz. Taslakta numara verilmez.
Biçim: `SF-2026-00001`, yıl başında sıfırlanır.

**audit_log** — kullanici, sirket, tablo, kayit_id, islem, eski_deger, yeni_deger, zaman, ip

**attachments** — sirket_id, ilgili_tablo, ilgili_id, dosya_yolu, tur, boyut, yukleyen
Her belgeye resim, PDF ve benzeri dosya eklenebilir.

### 2.2 Cari

**contacts** — sirket_id, kod, unvan, tip (tuzel | gercek), vergi_dairesi, vergi_no,
tc_no, adres, il, ilce, telefon, eposta, vade_gun (boşsa şirket varsayılanı),
risk_limiti, iskonto_yuzdesi, aktif, kaynak_sirket_id, kaynak_kayit_id

Tek kart; müşteri ve tedarikçi ayrı kart değildir, bakiye tektir.

**contact_categories** — tedarikçi, cari, internet müşterisi, mağaza müşterisi
**contact_category** — çoklu bağ; bir kart birden çok kategori taşıyabilir.

**contact_addresses** — sevk ve fatura adresleri
**contact_contacts** — yetkili kişiler
**contact_banks** — banka hesapları

**contact_transactions** — sirket_id, cari_id, tarih, belge_turu, belge_id,
borc, alacak, aciklama, vade_tarihi
Cari bakiyesi bu tablodan hesaplanır. Belge kesinleştiğinde satır oluşur;
iptal edilirse ters kayıt eklenir, satır silinmez.

### 2.3 Ürün ve stok

**products** — sirket_id, kod, ad, kategori_id, marka_id, birim_id, barkod,
kdv_orani, liste_fiyati (KDV hariç), negatif_stok_izni (bool), aktif,
varyant_grup_id, tur (normal | set | konfigure), kaynak_sirket_id, kaynak_kayit_id

Her varyant **ayrı karttır**. `varyant_grup_id` ile gruplanır.

**variant_groups** — sirket_id, ad, ozellikler (renk, ölçü, katman …)
**product_variant_values** — ürün kartının grup içindeki özellik değerleri
B2B ve kendi e-ticaret sitelerinde grup tek ürün, kartlar varyant olarak çıkar.
Pazaryerlerine şimdilik düz kart gönderilir.

**product_sets** — set_urun_id, bilesen_urun_id, miktar
Setin kendi stoğu yoktur. Satılabilir set adedi
`min(bileşen_stoğu ÷ gereken_miktar)` ile hesaplanır. Herhangi bir bileşen tek
seti bile karşılayamıyorsa set adedi sıfırlanır ve **tüm kanallarda satışa
kapatılır**. Satışta bileşenler stoktan düşer.

**config_definitions / config_options** — konfigüratör tanımları (gövde, kristal,
duy vb. seçim grupları ve seçenekleri)

**units / unit_conversions** — birim ve dönüşüm katsayıları

**locations** — sirket_id, kod, ad, tip (depo | şube | araç), aktif
Araç depo gibi davranır. Sıcak satışta merkez → araç transferi yapılır,
satış araç deposundan düşer, gün sonunda kalan geri transferle döner.

**stock_balances** — sirket_id, urun_id, lokasyon_id, miktar, rezerve,
konsinye_rezerve, karantina
`kullanilabilir = miktar − rezerve − konsinye_rezerve − karantina`

**stock_movements** — sirket_id, urun_id, lokasyon_id, tarih, tur, miktar (±),
birim_maliyet, belge_turu, belge_id, aciklama
Tüm stok değişimi bu tablodan geçer. Bakiye tablosu buradan türetilir.

**product_costs** — sirket_id, urun_id, son_alis, hareketli_ortalama,
ithalat_maliyeti, guncelleme_tarihi

### 2.4 Belgeler

Ortak başlık yapısı (**documents**): sirket_id, belge_turu, seri_no, tarih,
cari_id, para_birimi, kur, durum, vade_tarihi, iskonto_yuzdesi, iskonto_tutari,
ara_toplam, matrah, kdv_tutari, genel_toplam, notlar, olusturan, kesinlestiren

Satırlar (**document_lines**): belge_id, urun_id, aciklama, miktar, birim_id,
birim_fiyat (KDV hariç), satir_iskonto, kdv_orani, satir_toplam,
rezerve_edilsin (bool), konfigurasyon (JSON, dondurulmuş bileşen listesi),
kaynak_satir_id (kısmi işlem zinciri için)

Belge türleri: teklif, satış siparişi, irsaliye, satış faturası, proforma,
satınalma siparişi, mal kabul, alış faturası, satış iadesi, alış iadesi,
depo transferi, stok sayımı, ambar fişi, üretim emri, fason gönderi/kabul/iade,
ithalat dosyası, konsinye/numune.

---

## 3. İş kuralları

### 3.1 Fiyat ve tutar

Fiyat girişinde KDV **dahil veya hariç** seçilebilir; veritabanında her zaman
**KDV hariç** tutar saklanır.

Hesap sırası:

```
satır toplamı  = miktar × birim fiyat − satır iskontosu
ara toplam     = Σ satır toplamları
iskonto        = ara toplam × iskonto yüzdesi        ← KDV'den ÖNCE
matrah         = ara toplam − iskonto
KDV            = matrah üzerinden, satır oranlarıyla
genel toplam   = matrah + KDV
```

Fatura ve sipariş ekranında **"Tümüne KDV uygula"** ve **"KDV temizle"**
düğmeleri bulunur; temizlenirse satır oranları sıfırlanır.

İskonto kaynağı: cari kartındaki yüzde belgeye otomatik gelir, belgede
değiştirilebilir. Ürün+cari kırılımında özel fiyat yoktur.

### 3.2 Maliyet

Yöntem: **hareketli ortalama**, başka yöntem yok.

```
yeni ortalama = (mevcut miktar × mevcut ortalama + giren miktar × giren fiyat)
                ÷ (mevcut miktar + giren miktar)
```

Alış girişinde birim fiyat mevcut ortalamadan **±%25** saparsa uyarı verilir;
kayıt engellenmez, satır işaretlenir ve işlem geçmişine düşer. Eşik şirket
ayarından, ürün grubundan ezilebilir.

Ürün kartında dört değer ayrı tutulur: son alış, hareketli ortalama, ithalat
maliyeti, geçerli maliyet.

### 3.3 Stok

Negatif stok **ürün bazında** izinlidir; izinliyse uyarı verilir, izinli
değilse işlem engellenir.

Rezervasyon sipariş **satırı** bazında seçilir. Rezerve miktar stoktan
düşmez, kullanılabilir miktarı azaltır. Sevkiyatta rezerv çözülür, stok düşer.

Konsinye ve numune mal stoktan düşmez, `konsinye_rezerve` bakiyesinde durur.

Sayım farkı **elle onaylanır**; onaylanınca stok düzeltme hareketi oluşur.

### 3.4 Belge yaşam döngüsü

Taslak → kesinleşmiş. Kesinleşen belge **değiştirilemez ve silinemez**.
Düzeltme ters kayıtla yapılır, iki belge de görünür kalır. İptal edilebilir.

Onay yalnızca iki yerde: teklif (iç onay) ve satınalma siparişi (onaya gönder).
Diğer belgeler doğrudan kesinleşir.

Kısmi işlem: sipariş kısmi sevk edilebilir, sevkiyat kısmi faturalanabilir.
Kalan miktar takip edilir, "kalanı iptal" ile kapatılır.

Risk limiti aşımında **uyarı** verilir, işlem engellenmez.

Vade varsayılan **30 gün**, cari kartından ezilebilir.

### 3.5 İade

Satış iadesinde mal **karantinaya** girer. Kontrol sonrası "satılabilir"
işaretlenince stoğa geçer, "hurda" işaretlenirse çıkış yapılır.
Cari alacağı iade belgesi kesildiğinde oluşur, karantina çıkışını beklemez.

### 3.6 Üretim ve fason

Reçete **versiyonludur**; üretim emri kullandığı versiyonu saklar, sonradan
reçete değişse geçmiş üretimin maliyeti bozulmaz.

Akış: emir açılır → malzeme çıkışı (reçeteden gelir, elle değiştirilebilir) →
mamul girişi (kısmi olabilir) → emir kapanır.

Mamul maliyeti = çıkan malzemelerin maliyeti + varsa fason bedeli, üretilen
adede bölünür. İşçilik ve genel gider eklenmez. Fire, malzeme çıkışında
fazladan tüketim olarak girilir ve maliyete yansır.

Fasonda malzeme fason lokasyonuna transfer edilir, mülkiyet devam eder.
Gelen mamul fason lokasyonundan düşer, merkeze girer. Fasoncudaki malzeme
fason lokasyonu bakiyesinde görünür.

### 3.7 İthalat

Maliyet kalemleri (nakliye, gümrük, sigorta …) ürünlere **değer** üzerinden
dağıtılır; kalem bazında ağırlık veya hacim de seçilebilir.

Döviz yalnızca ithalat ve alış belgelerinde kullanılır. Maliyet, malın stoğa
girdiği tarihteki kurdan TRY'ye çevrilir ve **sabitlenir**; sonraki kur
değişimi stok maliyetini etkilemez. Kur farkı hesaplanmaz.

Dosya kapandığında maliyet ürün kartına işlenir ve sonraki hareketleri etkiler.

### 3.8 E-ticaret ve pazaryeri

Senkronizasyon **15 dakikada bir**, ayrıca ekranda "Şimdi senkronize et"
düğmesi. Aralık ayarlardan değiştirilebilir.

Ürün eşleştirmesi **elle** yapılır (yanlış eşleşme yanlış stok düşümü demektir).
Pazaryeri siparişi satış siparişi olarak düşer; hangi şirkete yazılacağı kanal
ayarında tanımlıdır.

Gönderilen stok, seçilen lokasyonların kullanılabilir miktarıdır. Kanal bazında
"ayrılan miktar" sınırı konabilir.

**Görsel setleri:** ürün görselleri set halinde tanımlanır (Ortak, Trendyol,
Hepsiburada, N11, Site A …). Kanal bir sete bağlanır; seti yoksa Ortak sete
düşer. İki WooCommerce sitesi aynı seti paylaşabilir.

### 3.9 Veri aktarımı

Açılış bakiyeleri ve kartlar **Excel ve JSON** ile içe aktarılır. Aktarım
parçalı çalışır, hatalı satırlar rapor edilir, tamamı işlem içinde uygulanır.

---

## 4. Açık kalan kararlar

1. **Stok gönderiminde çift satış riski.** 15 dakikalık aralık son adetlerde
   risk yaratıyor. Çözüm seçenekleri: kanal bazında ayrılan miktar sınırı,
   veya satış anında anlık stok gönderimi. Karar bekliyor.
2. **Varyant grubunun pazaryerlerinde** varyantlı gönderilmesi — sonraya bırakıldı.
3. **Konfigüratör fiyatlaması** — bileşen fiyatlarının toplamı mı, ayrı fiyat
   tablosu mu?
4. **Araç kasası** — tahsilat elle çözülecek denildi; ileride araç bazlı kasa
   istenirse model buna hazır.
5. **Fire yüzdesi** reçetede tanımlı olsun mu, yoksa yalnızca çıkışta elle mi?
6. **Konsolide rapor** hangi rollere açılacak?
7. **Dosya saklama** — görseller ve ekler için disk alanı ve yedekleme planı.

---

## 5. Sonraki adım

1. Bu belgenin gözden geçirilmesi, açık kararların kapatılması
2. Veritabanı şeması ve migration'lar
3. Filament kaynakları: önce cari, ürün, stok; sonra satış ve alış döngüsü
4. Açılış verisi aktarımı, gerçek veriyle paralel çalışma
