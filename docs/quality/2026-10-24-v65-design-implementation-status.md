# MarsProject — v65 tasarım uygulama / kabul durumu

Kaynak temel: onaylı v65 prototipi, 20 ekran kalite matrisi ve 24 Ekim tasarım denetimi.
Kapsam: uygulama arayüzü. Bu dosya **yayın veya test başarı sertifikası değildir**.

## Kaydedilen kodlar

1. Kullanıcı menüsü görünüm seçimi: açık / orta / koyu (resources/views/layouts/app.blade.php).
2. Tarayıcıda kalıcı görünüm tercihi ve Livewire sayfa geçişi (resources/js/app.js).
3. Ayrı tema stilleri ve baskı için açık renk değişkenleri (resources/css/v65-themes.css).
4. Metin/sayı/select/textarea bileşenlerinde Livewire hata durumu ve erişilebilir uyarı (resources/views/components/field).
5. Önceki paketteki klavye erişimli ana içeriğe geçiş ve ortak v65 sekme adaptörü korundu.
6. Nakit Akış Takvimi'ne responsive bilgi kartları eklendi; yetki kontrollü rapor tablosu değiştirilmedi (resources/views/livewire/reporting/report-center.blade.php, resources/css/v65-reporting.css).
7. Baskı önizleme bağlantılarındaki boş # URL'leri gerçek PDF/PNG URL'leriyle değiştirildi (resources/views/components/v65/document-print-options.blade.php).
8. Basit filtre çekmecesi kapanışı inline onclick yerine tek merkezden olay dinleyiciyle yönetilir.
9. Yeni CSS dosyaları hem Laravel @vite hem vite.config.js girişlerine kaydedildi.
10. Test kapsamı: V65ThemeAndFieldContractTest, V65ReportCalendarVisualContractTest, v65-theme-responsive-readonly, v65-twenty-screen-readonly.
11. Tekrarlanabilir kaynak taraması: node scripts/ci/audit-v65-design.mjs; her satırı statik örüntüler açısından tarar, artifacts/v65-design-source-audit.json üretir. Manual workflow_dispatch'a entegre edildi.

## 25 maddelik tasarım kapatma matrisi

Durumlar: **Kodlandı** = kaynakta değişiklik var, **Kısmi** = yalnız bir alt kapsam düzeltildi, **Bekliyor** = gerçek/manuel kabul veya kod işi açık.

| # | Başlık | Durum | Kalan gerçek kabul |
|---:|---|---|---|
| 01 | Onaylı v65 ile ekran eşleşmesi | Bekliyor | Dış 20 ekran HTML kaynağıyla 1:1 görsel karşılaştırma |
| 02 | Responsive ekran kabulü | Kısmi | 390/768/1024/1440/1920 gerçek tarayıcı koşumu |
| 03 | Çakışan CSS kuralları | Kısmi | Statik tarama mevcut, güvenli kaldırma için görsel regresyon gerekiyor |
| 04 | Gereksiz !important temizliği | Bekliyor | Çakışma bazlı, ekrana etkisi doğrulanan temizlik |
| 05 | Ortak renk değişkenleri | Kısmi | Yeni tema token'ları var, eski sabit HEX kullanımları kaldı |
| 06 | Üç tema | Kısmi | Seçim/kaydetme kodlandı; tüm modüllerde kontrast ve görüntü kabulü bekliyor |
| 07 | Buton tek standardı | Kısmi | v65 ortak komponenti mevcut; tüm ham buton kullanımları göç etmedi |
| 08 | Buton boyutu/hizalama | Kısmi | Mevcut v65 kuralı korunuyor; tüm modüllerde tarayıcı kabulü bekliyor |
| 09 | Form kontrolleri | Kısmi | Dört ortak alanın hata erişilebilirliği düzeltildi; tüm ham alanlar geçmedi |
| 10 | Eski/yeni modal | Bekliyor | Kullanımı bilinçli modüller gerçek klavye testiyle doğrulanmalı |
| 11 | Sekmeler ve klavye | Kısmi | Eski ürün sekmesi v65 adaptörüyle birleşti; tüm sekmeler test edilmeli |
| 12 | Popup z-index/taşma | Bekliyor | Telefon ve masaüstü ekranlarında katman kontrolü |
| 13 | A11y | Kısmi | Skip link, sekme ve form hata uyumu; tam WCAG kabulü bekliyor |
| 14 | Tablo/sayı hizası | Kısmi | Mevcut ortak tablo stilleri var; birebir ekran karşılaştırması eksik |
| 15 | Mobil geniş tablolar | Kısmi | Mevcut kaydırma katmanları ve tarayıcı testleri; çalışma sonucu bekliyor |
| 16 | Form hata/uyarı bileşenleri | Kısmi | Dört alan güncellendi, uygulama tamamı dönüşmedi |
| 17 | Kritik işlem onayları | Bekliyor | Her işlem ve yetki üzerinden interaktif test |
| 18 | Baskı/önizleme | Kısmi | # bağlantıları düzeltildi; PDF/A4/etiket çıktısı gerçek kabulde |
| 19 | Rapor grafik/takvim | Kısmi | Nakit Akış Takvimi kartları eklendi; diğer rapor grafikleri açık |
| 20 | Görsel regresyon | Kısmi | 20 ekran GET/tabanlı responsive senaryo yazıldı; piksel baseline yok |
| 21 | Inline JS/stil | Kısmi | Filtre kapanışı ve yazdırma URL'leri düzeltildi; başka kullanımlar kalıyor |
| 22 | Tekrarlanan eski komponentler | Kısmi | Eski sekme adaptörü güncellendi; eski modal ve özel kontroller kaldı |
| 23 | Sayfa başlığı/toolbar standardı | Bekliyor | Onaylı prototip ölçü/konum karşılaştırması |
| 24 | Empty/loading/success/error | Bekliyor | Tüm modüllerde durum matrisi ve görüntü kontrolü |
| 25 | Barkod/yazıcı/resim düzenleme donanımı | Bekliyor | Desteklenen cihazlarla gerçek donanım testleri |

## Test ve yayın kapısı

- Kodun GitHub'a commit edilmesi, gerçek tarayıcıda veya PostgreSQL'de test edildiği anlamına gelmez.
- Manual V65 source workflow isteğe bağlıdır; **bu iş kapsamında tetiklenmedi**.
- Playwright koşumu 127.0.0.1 test sunucusu + açık onay kapıları olmadan yapılmaz.
- 20 ekranın dış referans HTML'si bu repoda yok; onaylı v65 genel prototipi repo içinde mevcut.
- Üretim veritabanına, şirket mali kayıtlarına veya gerçek yazıcılara bu uygulama sırasında müdahale edilmedi.
- Canlıya yayın gerçekleştirilmedi.

Sonuç: Ortak arayüz geliştirmeleri GitHub'da mevcut; tüm 25 maddelik **profesyonel görsel kabul tamamlandı** iddiasında bulunulamaz.


## Sonraki kaynak düzeltme paketi — aynı tarih

GitHub ana dalına aşağıdaki dar kapsamlı düzenlemeler eklendi:

1. Ürün ve cari sekmelerinde etkin panelin `role="tabpanel"`, `aria-labelledby` ve sekmenin `aria-controls` ilişkisi eklendi. Livewire etkin olmayan panelleri DOM'dan kaldırdığı için `aria-controls` yalnız etkin sekmeye uygulanır.
2. Tarih ve dosya seçimindeki Livewire doğrulama hataları ortak alanlarla aynı `aria-invalid` ve `role="alert"` işaretlerini kullanır.
3. Uygulama kabuğunun Yazdır / Geri işlemleri satır içi JavaScript yerine delegasyonla yönetilir.
4. Bağımsız belge ve taşıma fişi HTML çıktılarının yazdırma eylemleri ortak `public/js/mars-print-actions.js` dosyasına taşındı. A4/A5 baskı düzenleri ve otomatik yazdırma seçeneği korunmuştur.
5. Cari adres, yetkili ve banka düğmeleri ortak `x-v65.button` bileşenine taşındı; mevcut `wire:click` ve silme onayları korundu.
6. Ürün set bileşenlerinin tablosu dar ekran için kaydırma kapsayıcısına alındı ve kalıcı bileşen silinmesinde kullanıcı onayı zorunlu hale getirildi.
7. Rapor kataloğuna `aria-pressed`, rapor hata ve işlem sonucuna canlı bölge rolleri eklendi.
8. 20 onaylı ekranın salt-okunur tarayıcı test kapsamı 390, 768, 1024, 1440 ve 1920 px genişliklerine çıkarıldı.
9. Yeni `V65DesignCompletionContractsTest` ve ürün/cari sekme tarayıcı sözleşmeleri eklendi; önceki sekme birim testi yeni adaptöre göre güncellendi.

**Bu işlemler kaynak koduna uygulanmıştır; başarıyla çalıştırılmış test veya canlıya yayın iddiası değildir.**

Açık kapanış kapıları değişmedi: Onaylı haricî 20 HTML ekranla piksel bazında karşılaştırma, tema kontrastı ve cihaz geçişlerinde gerçek tarayıcı testi, tüm modüllerde CSS çakışma temizliği, desteklenen yazıcı/barkod donanımıyla çıktı kabulü ve deployment. Kaynak üzerinde çalışan olmayan donanım veya onaylı referans görseller olmadan bu maddeler `Tamamlandı` işaretlenemez. Üretim veritabanına müdahale edilmedi.
