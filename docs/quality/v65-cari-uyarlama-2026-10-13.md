# MarsProject – V65 Cari/Tablo Tasarım Uyarlama (13.10.2026)

Kaynak: `reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html` dosyası ve kullanıcı tarafından gönderilen ekran görselleri. **Bu uygulama kaynak kod değişikliğidir; testlerin veya Fresh dağıtımının yapıldığı anlamına gelmez.**

## Temel ilkeler
1. Onaylı HTML'nin Cari **detay** içerik düzeni referanstır; bu ekran genel liste şablonuna zorlanmaz.
2. Cari, ürün, stok, ambar ve diğer listelerde son onaylanan ortak **üst işlemler, filtre çubuğu, kompakt sonuç tablosu, alt sayfalama/kolonlar popup** korunur.
3. Prototipteki Avize Park, tarih, tahsilat, ciro, B2B örnekleri gibi veriler **kesinlikle sabitlenmez**; yalnız dönem veritabanındaki gerçek kayıtlar okunur.
4. Kesinleşmiş ve taslak hareketler karıştırılmaz. Muhasebe ekranlarında para birimleri birleştirilmez. Özellikle `contact_transactions` hareketleri `currency` bazında izlenir.

## Uygulanan ekranler
- Cari Listesi: izinli detay bağlantısı, satır işlemleri ve gerçek cari/TRY bilanço özeti; ortak DataTable başlık ve alt şerit.
- Cari Detay: yeni `contacts.show` route; düzenleme ayrı kalır. Üst sağdaki izinlere bağlı Alım/Satış/İade/Diğer; Yazdır/Geri. Sekmeler ürün detayından bağımsız.
- Genel Bakış: cari kimliği, gerçek bakiye/para birimi, cari hareketler, devam eden işler, iletişim özeti, ticari koşullar.
- Faturalar, Siparişler, Teklifler: duruma ve belge türüne özgü kolonlar, belge bağlantıları, pagination. Teklif geçerlilik tarihi eklendi.
- Cari Hareketler / Ekstre: tarih aralığı, geçmiş kayıtları kapsayan devir bakiyesi, para birimi bazında kronolojik running balance; detaysız, detaylı fiş satırları, fiş listesi modları, **CSV ve XLSX indirme**.
- Ürün Analizi: gönderilen onaylı HTML'ye karşılık 2 kolon; TRY kesinleşmiş satışlardan en çok alınan ürünler, kategori dağılımı, son ürün satırları ve analiz özeti.
- Yıllık Performans: aylık TRY satışları + yıllık para birimi ayrı toplam; gerçek veriye dayalı metrik kartları.
- Adresler: kart yerleşimi, fatura/sevk/varsayılan rozetleri, düzeltme bağlantısı.
- İletişim: firma iletişim ve yetkili kartları, birincil etiket.
- Fatura Bilgileri, Sevk Bilgileri: dört sütunlu kompakt metin alanları; tanımlı olan gerçek cari alanları.
- Banka Bilgileri, Özel Açıklamalar, B2B, Dosyalar: kayıtlı alanların gösterilmesi; mevcut şemadaki boşlukların uydurulmaması.

## Bilinen sınırlar ve eksikler
- Onaylı HTML'deki E-Fatura etiketi, bazı sevk parametreleri, ek banka alanları, B2B portal istatistikleri, ayrı özel/iç not, risk sınıfı/trend analizleri mevcut cari modelinde yoktur; ek tablo/migration uydurulmamıştır.
- Cari Ekstre ve Fiş Listesi bağımsız rotalar değil; Cari Detay `movements` altındaki 3 işlevsel görünüm olarak sunuluyor.
- Kasa/mahsup muhasebe doğrulaması, erişim yetkileri, CSV/XLSX çıktı içeriği, farklı para birimi ve dönem filtreleri, tarayıcı boyutları için **çalıştırılabilir entegrasyon/E2E testleri henüz koşturulmadı**.
- Bütün yazılımın iş ekranları için **tek seferde birebir kabul verilmiş değildir**; yukarıdaki değişiklikler Cari Detay'a yoğunlaşır.
- PROD/Fresh servisleri, demo veriler, migration ve Docker konfigürasyonu **değiştirilmedi**.

## Kabul adımları
1. `php -l` (ContactDetail, ContactList), Laravel view cache kontrolü, seçilen test dosyalarının çalıştırılması.
2. Fresh Cari Listesi → gerçek cariye tıklayarak detay; yetkisiz kullanıcılarda öğelerin yokluğu.
3. Boş ve dolu veride her sekme, ürün bağlantıları, iptal/taslak/kesinleşmiş durumlar.
4. Para birimleri TRY ve USD karışıkken bakiye / CSV / Excel, tarih aralığı dışı devir bakiyesi.
5. 768 / 1024 / 1440 / 1920 ekran genişliklerinde üst ve alt bar, sekmelerin yatay kayması, adres kartları ve tablo satır yoğunluğu.
