# MarsProject V65 — Belge Ekranları: Parça Parça Uygulama Kaydı

Tarih: 15.10.2026
Başlangıç main HEAD: `730f0ae0fcda8ef11a64bb6139f9b7149375fed3`

## Kapsam

Satış Teklifi, Satış Siparişi, Satış İrsaliyesi, Satış Faturası, Alış Siparişi, Mal Kabul ve Alış Faturası. Ortak satış ve alış editörleri değiştirildi; belge kayıt servisleri ve veritabanı tabloları değiştirilmedi.

## Bölüm 1 — Finansal bilgi (GitHub kaynak kodu)

- [x] Satış faturasında `contacts.view` yetkisine bağlı TRY cari bakiye, risk limiti, kalan limit ve bilinen risk görünümü.
- [x] Cari borçlu (kırmızı), cari alacaklı (yeşil) ve dengede durum etiketleri.
- [x] Kesinleşmiş faturayı cari bakiyeye ikinci kez eklememek.
- [x] Satır iskonto tutarı, satır net/matrah ve *bilgi amaçlı* satır KDV sütunları.
- [x] Belge genel iskontosu öncesi satır KDV uyarısı; nihai KDV sunucu toplamından gelir.
- [x] Kaydedilmemiş alış belgesinde seçilen para birimine göre toplamları etiketlemek.

**Dikkat:** Satır bazındaki KDV sütunu, belge iskontosu dağıtımı sonrası resmi satır vergisi değildir. Resmi dağıtım için hesaplama servisi ve finansal kabul testi gerekir. TRY risk görünümü teminat ve tüm para birimlerini kapsamaz; onay motorunun yerine geçmez.

## Bölüm 2 — Kullanıcı işlemleri ve doğrulama

- [x] `x-v65.document-confirm` ile onay, kesinleştirme, alış onaya gönderme ve ters kayıt için kayıtlı belge numarası/cari/toplam üzerinden ayrı onay diyaloğu.
- [x] Sunucu tarafında tarih, pozitif miktar, sıfırdan küçük olmayan fiyat, yüzde aralıkları ve kur doğrulaması.
- [x] `x-v65.input-error` ile form alanı hata metinleri ve `aria-invalid`; Livewire sonrası ilk geçersiz girdiye odaklanma.
- [x] Risk kabulü, fiyat sapması kabulü ve mevcut dönem yetki kontrollerinin bulunduğu Livewire metotları değişmeden korunur.
- [x] İşlem düğmelerinde tekrar tıklama azaltmak için `wire:loading.attr`.

## Bölüm 3 — Ortak V65 görünüm

- [x] Ürün/cari aramasında boş sonuç ve arama yükleniyor mesajları; ilk 35 sonucun sınırlı biçimde gösterilmesi.
- [x] 1150 px altında satır kimliğinin sabit kalması ve tablo yatay kaydırma açıklaması.
- [x] Rezerv, irsaliye, fatura, proforma ve alış gönderim düğmelerinin ortak V65 buton stili.
- [x] Finansal risk, satır tutarları, form doğrulama ve modal detayları için responsive CSS.
- [x] Kaydedilmiş satır/belge toplamlarının baskı görünümü ayrımı korunur.

## Hazırlanan kod kalite sözleşmeleri

- `tests/Unit/UI/V65DocumentEditorPhase2Test.php`
- Önceki `tests/Unit/UI/V65DocumentEditorDesignTest.php`

Test **kodları hazır**, ancak testler **çalıştırılmadı**. Kaynak düzeyinde V65 Blade koşul ve komponent etiket eşleştirmesi kontrol edildi. Bu kaynak taraması runtime veya PHP/Blade derlemesi yerine geçmez.

## Açık kalan ve ayrıca doğrulanacak işler

1. **Kritik:** PHP/Blade/Vite build, birim ve Livewire testlerinin kullanıcı onayıyla çalıştırılması.
2. **Kritik:** Türkçe virgül, fiyat, iskonto, çoklu KDV, para birimi/kur, risk ve belge kesinleştirme regresyon testleri.
3. **Kritik:** Teklif → Sipariş → İrsaliye → Fatura ve Alış Siparişi → Mal Kabul → Alış Faturası uçtan uca testleri.
4. **Yüksek:** 390/768/1024/1440/1920 px onaylı V65 HTML ile gerçek tarayıcı karşılaştırması; responsive davranışın kabulü.
5. **Yüksek:** Resmi/kurumsal fatura, teklif, irsaliye PDF/çıktı şablonlarının tasarım ve mevzuat kabulü. Mevcut yazdırma tarayıcı baskısıdır; e-fatura veya UBL yerine geçmez.
6. **Yüksek:** Fresh UAT ortamına ayrı onayla kontrollü yayın ve kullanıcı kabul testi.
7. **Yüksek:** Belge iskontosunun her satıra dağıtılmış kesin KDV miktarı isteniyorsa hesaplama servisi ve belge politikası için ayrıca karar/veri doğrulaması.

Bu aşamada PROD, Docker, PostgreSQL/Valkey, GitHub workflow yürütme veya onaylı ambar fişi baskı şablonlarına dokunulmadı.
