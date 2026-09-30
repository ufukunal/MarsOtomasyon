# MarsOtomasyon — Proje Planı v1

Dayanak: `mars-sartname-v1.md`. Kapsam ön muhasebe + basit üretim,
teknik zemin Laravel + Filament + PostgreSQL.

Süreler tek geliştirici (sen) + benim kod üretimim varsayımıyla, tam zamanlı
olmayan bir tempoya göre verildi. Yarı zamanlı çalışırsan iki katına çıkar.

---

## Çalışma yöntemi

**Depo.** Claude Code ile bağlanmak en verimlisi; dosyaları okuyup yazabilir,
commit atabilir. Alternatif olarak dosyaları burada üretirim, sen commit edersin.

**Parça büyüklüğü.** Her iş bir faz değil, bir **dilim** olarak yapılır:
tek tablo + tek ekran + testi. Büyük parçalar hem hata saklar hem geri
almayı zorlaştırır.

**Benim sınırlarım.** Tarayıcıda hiçbir şey göremiyorum. Kod ürettiğimde
mantığı test edebilirim ama ekranın nasıl göründüğünü göremem. Görsel
doğrulamayı sen yapacaksın; ekran görüntüsü gönderirsen değerlendirebilirim.

**Karar günlüğü.** Şartnamedeki açık kararlar kapandıkça belgeye işlenir.
Kod, şartnameyle çelişiyorsa şartname güncellenir — tersi değil.

**Her fazın bitiş ölçütü:** ekranlar açılıyor, veri kaydediliyor, ilgili
rapor doğru rakamı veriyor, yetkisiz kullanıcı erişemiyor, işlem geçmişine
kayıt düşüyor.

---

## Faz 0 — Temel (1 hafta)

Projenin iskeleti. Bu faz atlanırsa sonraki her şey yeniden yazılır.

- Repo, Laravel + Filament kurulumu, ortam ayarları
- Kimlik doğrulama, kullanıcı, rol, izin (ekran bazlı + `maliyet_gor`)
- **Şirket modeli ve izolasyon** — global scope, aktif şirket seçici
- `company_links` — şirketler arası kopyalama izni altyapısı
- `number_series` — kilitli numara üretimi
- `posting_periods` — dönem kilidi
- `audit_log` — otomatik kayıt
- `attachments` — belgelere dosya ekleme
- Yedekleme betiği ve harici kopyalama

**Kritik:** şirket izolasyonu en baştan global scope olarak kurulmalı.
Sonradan eklenirse her sorguyu tek tek gözden geçirmek gerekir.

---

## Faz 1 — Kartlar (1–2 hafta)

- Cari: kart, kategoriler, adresler, yetkililer, bankalar, vade, risk limiti, iskonto
- Ürün: kart, kategori, marka, birim ve dönüşüm, barkod, KDV, liste fiyatı
- **Varyant grubu** ve grup içi özellik değerleri
- **Set ürün** tanımı (bileşen listesi)
- **Konfigüratör** tanımları ve seçenekleri
- Lokasyon: depo / şube / araç
- Fiyat listeleri
- Şirketler arası **kopyalama** işlemi (kaynak referansı ile)
- **Excel / JSON içe aktarma** — kartlar ve açılış bakiyeleri

---

## Faz 2 — Stok (1–2 hafta)

- `stock_movements` — tek gerçek kaynak, bakiye buradan türetilir
- Bakiye görünümü: miktar / rezerve / konsinye rezerve / karantina / kullanılabilir
- **Hareketli ortalama maliyet** + %25 sapma uyarısı
- Depo transferi, ambar fişi
- Stok sayımı ve **elle onaylı** fark düzeltmesi
- Karantina giriş/çıkış
- Negatif stok kontrolü (ürün bazında izin)
- Set ürün satılabilirlik hesabı

---

## Faz 3 — Satış (2 hafta)

- Teklif → sipariş → irsaliye → fatura zinciri
- **Kısmi sevk ve kısmi fatura**, kalan takibi, "kalanı iptal"
- Satır bazında **rezervasyon** seçimi
- İskonto (KDV'den önce), toplu KDV uygula / temizle
- Konfigüratörlü satır (bileşen listesi dondurulur)
- Araçtan sıcak satış: transfer + doğrudan fatura
- Tahsilat ve cari hareket
- Proforma

---

## Faz 4 — Alış (1–2 hafta)

- Satınalma siparişi (onaylı) → mal kabul → alış faturası
- Ödeme ve cari hareket
- Üçlü eşleştirme
- Alış girişinde maliyet güncelleme ve sapma uyarısı

---

## Faz 5 — Kasa / Banka (1 hafta)

- Kasa ve banka hesapları, hareketler, virman
- Gider kaydı, avans, para iadesi
- Çek / senet: giriş, çıkış, bordro, kısmi tahsilat
- Banka ekstresi içe aktarma ve mutabakat

---

## Faz 6 — İade (1 hafta)

- Satış iadesi → karantina → satılabilir / hurda
- Alış iadesi
- İade merkezi ekranı, cari ve stok etkileri

---

## Faz 7 — İthalat (2 hafta)

- Dosya, konteyner, koli / parça eşleştirme
- Maliyet kalemleri ve **değer bazlı dağıtım** (ağırlık / hacim seçenekli)
- Giriş tarihindeki kurdan sabitleme
- Dosya ve konteyner **kârlılık** raporları
- Üretime / fasona hazırlık

---

## Faz 8 — Üretim ve fason (2 hafta)

- **Versiyonlu reçete**
- Üretim emri → malzeme çıkışı → mamul girişi (kısmi) → kapanış
- Mamul maliyeti (malzeme + fason bedeli), fire
- Fason: gönderi, kabul, iade, mutabakat, fason lokasyonu bakiyesi

---

## Faz 9 — E-ticaret ve pazaryeri (2–3 hafta)

- Kanal tanımları, kimlik bilgileri
- **Elle ürün eşleştirme**
- Sipariş çekme (15 dk + elle tetikleme)
- Stok ve fiyat gönderme, kanal bazında ayrılan miktar
- **Görsel setleri** (Ortak / Trendyol / Hepsiburada / N11 / site bazlı)
- B2B ve kendi sitelerinde **varyant grubu** olarak yayın
- Hata kuyruğu ve yeniden deneme

**Not:** en çok sürprizin çıkacağı faz burası. Her pazaryerinin API'si farklı
davranır; tek tek ele alınmalı, hepsini aynı anda bağlamaya çalışma.

---

## Faz 10 — Raporlar ve çıktılar (1 hafta)

- Stok Değeri (maliyet / liste / ortalama satış fiyatı)
- Kârlılık, stok yaşlandırma, cari yaşlandırma, nakit akış
- Yazdırma çıktıları: cari kartı, ürün kartı, belge dökümleri, analiz raporları
- Excel / CSV dışa aktarma (liste ekranlarında ortak davranış)

---

## Faz 11 — Canlıya geçiş (1–2 hafta)

- Gerçek veri aktarımı ve doğrulama
- **Paralel çalışma dönemi** — eski yöntemle karşılaştırma
- Kullanıcı eğitimi, yetki dağıtımı
- Yedekleme ve geri yükleme provası (denenmemiş yedek yedek değildir)
- Dönem kilidi ve açılış bakiyelerinin kapatılması

---

## Toplam ve sıralama mantığı

Kabaca **4–5 ay**. Sıralama rastgele değil: her faz bir öncekinin ürettiği
veriye dayanıyor. Stok hareketi olmadan satış, satış olmadan iade, maliyet
olmadan kârlılık yazılamaz.

**Erken kullanıma alınabilecek nokta:** Faz 4 sonunda satış ve alış döngüsü
gerçek veriyle çalışır hale gelir. Kalan fazlar devam ederken sistem
kullanılmaya başlanabilir.

**Sıra değiştirilebilecek yerler:** Faz 7 (ithalat) ve Faz 8 (üretim)
birbirinden bağımsız, hangisi daha acilse öne alınabilir. Faz 9 en sona
bırakılabilir.

**Sıra değiştirilemeyecek yerler:** Faz 0 ve 1 her şeyin önünde. Faz 2 (stok)
satıştan önce gelmeli.

---

## Riskler

**Şirket izolasyonu sonradan eklenirse** her sorgu yeniden gözden geçirilir.
Faz 0'da halledilmeli.

**Pazaryeri API'leri** tahmin edilenden uzun sürer. Faz 9 için verilen süre
iyimser; tek kanalla başlayıp genişlet.

**Veri aktarımı** her projede küçümsenir. Mevcut verinin kalitesi düşükse
temizlik işi başlı başına bir faz olur.

**Tek kayıt riski.** Arkanda resmi bir defter yok; yedekleme ve dönem kilidi
ertelenebilir işler değil, Faz 0'ın parçası.
