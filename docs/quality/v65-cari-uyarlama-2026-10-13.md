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

## 13 Ekim 2026 — Devir sonrası somut düzeltmeler ve kabul sınırları

Başlangıç main HEAD: fe78894fc3717b35bce9e376658687bc12da6818.

Commit'ler:
- e711de4c78a90dec6512441af5386ef47d22e4a2 — Devir olup filtre aralığında hareketi olmayan dövizleri ekstre kartlarında koruma; CSV/XLSX devir/son satırlarını ekleme.
- cc5a0cc5fd2b5b73a94d2c39f07e470a8c041380 — Tek tutarlı Cari::balance() hesabını TRY ile sınırlandırma.
- 5afc00775f6d6469481a115a2d5a30a08e805e73 — Risk filtresi TRY ile sınırlandırıldı, Cari Liste kolonu Bakiye (TRY).
- 3a4bae354affdc77939a80626927972eefc885fd — İki kaynak sözleşmesi testi eklendi.
- 0335acc67dd44a819a43bbcd0b7cee0db65a7832 — CSV enjeksiyonuna karşı korunurken gerçek negatif bakiye değerleri bozulmuyor.
- 1864dd58afafa2ddf52f923feaa2c102aa864398 — CSV metin güvenliği ve negatif tutarlar için çalışma zamanı test senaryosu.

Referans: Onaylı V65 HTML GitHub blob SHA c11eb5f38bfa4d98f45c3c0758f692ff32bcf974. 19 temel Livewire ekran/form sınıfının GitHub'da bulunduğu doğrulandı. Bu yalnız kaynak varlığı kanıtıdır, görsel veya işlevsel kabul değildir.

| Alan | Durum | Açıklama |
|---|---|---|
| GitHub kod okuma / commit | TAMAM | Yukarıdaki altı değişiklik |
| Devir-only para birimi ekstre kartı | KODDA DÜZELTİLDİ, TEST EDİLMEDİ | ContactDetail |
| Çoklu dövizin skaler toplamda karışması | KODDA DÜZELTİLDİ, TEST EDİLMEDİ | Contact / ContactList |
| CSV negatif rakam koruması | KODDA DÜZELTİLDİ, TEST EDİLMEDİ | csvSafeRow |
| Ekstre CSV/XLSX gerçek dosya ve bakiye testi | DOĞRULANMADI | Fresh işlem verisine erişim yok |
| Cari yetki matrisi ve dönem izolasyonu | KISMEN İNCELENDİ | Kaynakta yetki kontrolleri görüldü |
| 19 ekranın tarayıcı ve responsive karşılaştırması | DOĞRULANMADI | 390/768/1024/1440/1920 px kontrol gerek |
| Cari Bakiyeleri / Fiş Listesi / Ekstre menüsü | KISMEN TAMAM | V65 menüsünde bağımsız; Laravel'de Ekstre/Fiş modları Cari Detay Hareketler altında. Bağımsız rotalar bulunmadı |
| Barkod, Paket, Maliyet Faktörü bağımsız ekranları | DOĞRULANMADI | İlişkili veri ve işlemler bulunması tek başına ekran kabulü değildir |
| Otomatik ve entegrasyon testleri | ÇALIŞTIRILMADI | Önceki ayrı onay şartı korundu |
| PostgreSQL kayıt sayıları | DOĞRULANMADI | Demo veri sıfırlaması veya tekrar import yok |
| Fresh web imajı / HTTP / health | DOĞRULANMADI | Sunucuya doğrudan erişim yok |

V65 HTML'nin son durumuyla Cari Detay, Cari Bakiyeleri, Fiş Listesi ve Cari Ekstre ayrı navigasyon başlıkları olarak karşılaştırıldı; mevcut uygulamanın bazı işlevleri ortak Hareketler sekmesinde topladığı belirlendi. Bu fark kullanıcı kabulü gerektiriyor; çalışan akışa izinsiz paralel ekran eklenmedi.

Fresh web dağıtım betiği ops/uat-fresh-web-update.sh kaynak düzeyinde okundu. Betik kaynak commit SHA'yı gerektiriyor ve web imajı oluşturma, eski imajı tutma, yalnız web'i yenileme, PG/Valkey ID ve volume doğrulama, HTTP kontrolü ile rollback adımlarını içeriyor. Okunmuş olması yayınlandığı anlamına gelmez. Yalnız Fresh web hedeflenebilir; PROD, migration, PostgreSQL, Valkey ve veri volume'lerine müdahale yok.

Kalan kabul: PHP/Blade/Vite denetimi, finans testleri ve indirmeler, tarayıcı boyutları, ayrı navigasyon kararı ve Fresh sürüm sonrası doğrulama. Her başarısızlık kanıtla raporlanmalı; çalıştırılmamış hiçbir test başarılı yazılmamalı.
