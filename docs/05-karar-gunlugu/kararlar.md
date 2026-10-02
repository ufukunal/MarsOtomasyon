# Karar günlüğü

Verilen kararlar ve gerekçeleri. **Kod bu kararlara uyar; kod kararla
çelişirse kod değişir, karar değil.** Karar değişecekse önce buraya işlenir.

| No | Karar | Gerekçe |
|---|---|---|
| K-001 | Laravel + Livewire, kendi bileşenlerimiz | Filament'in görsel dili istenmedi |
| K-002 | Genel muhasebe kapsam dışı | Gayri resmi sistem, ön muhasebe yeterli |
| K-003 | Resmi ve gayri resmi **ayrı şirket** | Tam izolasyon isteniyor |
| K-004 | Şirketler arası yalnız **izinli kopyalama**, kopya kayıt | Canlı bağ izolasyonu deler |
| K-005 | Parti/lot takibi kapsam dışı | İstenmedi; ithalat maliyeti ortalamaya karışacak |
| K-006 | Maliyet: **hareketli ortalama**, tek yöntem | Sadelik |
| K-007 | Alış fiyatı ±%25 saparsa uyarı, engel yok | Hatalı giriş yakalansın, iş durmasın |
| K-008 | İskonto **KDV'den önce**, matrahı düşürür | Fatura standardı |
| K-009 | Fiyat girişinde KDV dahil/hariç seçilir, **hariç saklanır** | Tek doğru kaynak |
| K-010 | Her varyant **ayrı kart**, üstünde grup | Stok ve barkod varyant seviyesinde |
| K-011 | Set ürünün kendi stoğu yok; `min(bileşen/gerekli)` | Bir bileşen bitince tüm kanallarda kapanır |
| K-012 | Dosya **sıkıştırması yok** | Kullanıcı elle yapacak |
| K-013 | Döviz yalnız ithalat ve alış; kur girişte sabitlenir | Kur farkı hesaplanmaz |
| K-014 | Araç = depo; sıcak satışta doğrudan fatura | Tahsilat elle çözülecek |
| K-015 | İade karantinaya girer, kontrolden sonra stoğa | — |
| K-016 | Kesinleşen belge değiştirilemez, ters kayıtla düzeltilir | Tek kayıt güvenliği |
| K-017 | Dönem ay bazında kilitlenir, yalnız Yönetici açar | Geçmiş rakam oynamasın |
| K-018 | Pazaryeri senkronizasyonu 15 dk + elle tetikleme | — |
| K-019 | Görseller platform bazlı **set** olarak tutulur | Kanal başına farklı görsel |
| K-020 | Katalog modülü **yapılmayacak** | Canva ile elle hazırlanacak |
| K-021 | VDS (6 CPU / 8 GB / 55 GB), PostgreSQL + Valkey | Paylaşımlı hosting yerine |
| K-022 | Yazdırma tek arayüz arkasında; taşıyıcı değişebilir | İleride özel tarayıcı kabuğu gelecek |
| K-023 | Belge tasarımcısı **bölüm tabanlı**, sürükle-bırak değil | Sayfa taşması sorunsuz çalışsın |
| K-054 | **Kartlar da dönem veritabanında**; master yalnız şirket, dönem, kullanıcı, yetki, kur tutar | Dönemin anlamı temiz geçiş; arşiv yıl kendi başına açılır |
| K-055 | Devirde **kartlar da kopyalanır** (cari, ürün, fiyat, lokasyon, konfigürasyon) | Kart o yılın içinde donar |
| K-056 | `company_copy_permissions` **Master DB'de ayrı tablo olarak kalır**; kaynak→hedef şirket kopyalama iznini tutar | Kopyalama period DB'ler arasında, yetki Master'da |
| K-057 | Pazaryeri stoğu **satış anında anlık** gönderilir; 15 dk tarama yedek | Çift satış riski (A-001) |
| K-058 | Ürün bazında `channel_stock_mode`: stok / üretim / elle | Bazı ürünler üretimden karşılanıyor |
| K-059 | **Konfigüratör fiyatı etkilemez**; yalnız ürün özelliği tanımlar | A-002 |
| K-060 | Reçetede fire yüzdesi **yok**, çıkışta elle girilir · Konsolide rapor **ayrı izinle** · Koli etiketi **ambar fişine** bağlı · Pazaryerleri **varyantsız** · **Kalite modülü kapsam dışı** · Satınalma talebi + teklif toplama **basit haliyle eklendi** | A-003, A-004, A-006, A-007 |
| K-061 | Etiket yazdırma **marka/model bağımsızdır**; ZPL şablonu genel, ölçü yazdırma profilinden gelir; `printer_name` yalnız fiziksel cihaz eşlemesidir | A-005 kapatıldı; K-066 alanları netleştirir |
| K-048 | **Stok hareketi her zaman ürünün temel biriminde**; belge satırı `base_quantity` ve dondurulmuş `conversion_factor` saklar | Birim karışırsa stok katlanır |
| K-049 | Dönüşüm tanımlı değilse işlem **engellenir**; katsayı 1 varsayılmaz | Sessiz yanlış stoktan iyidir |
| K-050 | Fiyat çözümleme: cari listesi → varsayılan liste → `products.list_price` → 0 | Tanımsızdı |
| K-051 | ~~Satır fiyatı %20+ sapmada `prices.override` ister~~ → **K-077 ile değiştirildi** | Yeni karar blok yerine uyarı + audit |
| K-052 | Maliyetin altında satışta uyarı; `cost.view` yoksa metin **maliyetsiz** gösterilir | Maliyet sızmasın |
| K-053 | Arşiv dönem geri yüklenince önce `migrate:periods`, sonra `closed` yapılır | Eksik şema hata verir |
| K-041 | Dağıtımda `migrate` değil **`migrate:periods`**; tüm dönem veritabanları güncellenir | Atlanan dönem sonra patlar |
| K-042 | Aranan alanlar için **normalize `search_index` kolonu** + trigram indeksi | PostgreSQL Türkçe'de yanlış sonuç verir |
| K-043 | İş kuralı hatası (`DomainException`) **loglanmaz**, kullanıcıya gösterilir | Log kirlenmesin |
| K-044 | Her istekte **correlation id**; beklenmeyen hatada kullanıcıya kod verilir | Destek izlenebilir olsun |
| K-045 | Dosya yüklemede **MIME içerikten** doğrulanır, ad UUID ile üretilir, **SVG yasak** | En geniş saldırı yüzeyi |
| K-046 | TC kimlik numarası maskelenir; tam hali ayrı izne bağlı (KVKK) | Teknik önlem; hukuki taraf danışmana |
| K-047 | Testler **gerçek PostgreSQL'e** karşı çalışır, SQLite'a değil | CHECK, lockForUpdate, trigram SQLite'ta yok |
| K-035 | Tutarlar **float değil**, string + BCMath (`Money` nesnesi) | PHP float'ta kuruş farkı birikir |
| K-036 | Yuvarlama **yalnızca belge toplamında**, 2 hane, yarım yukarı; KDV oran grubu bazında tek seferde | Satır satır yuvarlama sapma üretir |
| K-037 | Yuvarlama farkı `rounding_difference` alanında **saklanır** | Gizlenmez, bütünlük kontrolü hesaba katar |
| K-038 | Durum değiştiren her istek **istek anahtarı** taşır | Çift tıklama / ağ tekrarı iki belge oluşturmasın |
| K-039 | Düzenlenebilir tablolarda **`version` kolonu** (iyimser kilit) | İkinci kullanıcı birincinin değişikliğini ezmesin |
| K-040 | Dönem kilidi ve raporlar **`document_date`'e** bakar, `created_at`'e değil | Geriye dönük kayıt kontrolü |
| K-034 | **Türetilmiş veya kopyalanmış her değer için `integrity:` kontrolü yazılır**; fazın bitiş ölçütüdür | Kontrolsüz türetilmiş veri sessiz bozulmaya açık |
| K-031 | İhlal edilemez kurallar **CHECK kısıtı** olarak veritabanında | Uygulama atlanabilir, kısıt atlanamaz |
| K-032 | Belge kesinleştirmede **yazma sonrası doğrulama** aynı transaction içinde | Yarım yazılmış veri kalmasın |
| K-033 | Gecelik `integrity:all`; fark bulunursa bildirim, **otomatik düzeltme yok** | Sebep bilinmeden düzeltmek hatayı gizler |
| K-029 | Önbellek anahtarları **şirket ve dönem taşır** (`c1:y2026:...`); bağlam yoksa istisna | Global scope önbelleği korumaz |
| K-030 | Stok ve cari bakiyesi **önbelleğe alınmaz** | İşlem anında doğru olmalı |
| K-025 | **Master + şirket/dönem veritabanı**: `MarsProject_Master` + `ABCHolding_2026` | Logo/Mikro modeli; dönem izolasyonu ve arşivleme |
| K-026 | ~~Kartlar master'da~~ → **K-054 ile değiştirildi**: kartlar dönem veritabanında | — |
| K-027 | Kartlar aynı veritabanında → **gerçek yabancı anahtar** kullanılır; kod kopyası kolaylık içindir | K-054 sonrası |
| K-028 | Yıl sonu **dönem devri** ayrı bir işlem; hareketli ortalama kapanıştan açılışa taşınır | Maliyet sürekliliği |
| K-024 | Dosya diski soyut; dolunca S3 uyumlu servise geçilir | iDrive, Hetzner |


## 2026-10-01 — mimari ve satış öncesi ek kararlar

| No | Karar | Gerekçe / teknik sonuç |
|---|---|---|
| K-062 | **Cari bakiye hareket esaslıdır.** Dönem DB'deki `contact_transactions` bakiyenin tek gerçek kaynağıdır. Tahsilat/ödeme/çek/senet için fatura eşleştirmesi zorunlu değildir. Fatura içinden tahsilatta kaynak belge ilişkisi bilgi amaçlı tutulabilir. | Toptancı açık hesap yapısında bakiye, fatura kapatma tablosuna bağımlı olmamalı. |
| K-063 | **Cari yaşlandırma bilgilendirme amaçlı FIFO mahsuptur.** Cariyi azaltan kesinleşmiş hareketler en eski açık borçtan başlayarak sanal olarak kapatılır. Kalıcı fatura–tahsilat eşleştirmesi yazılmaz. Yaşlandırma temel tarihi `due_date`; dilimler: vadesi gelmemiş, 1–30, 31–60, 61–90, 91–120, 120+. | Gerçek bakiye hareketlerden, yaşlandırma raporu sanal dağıtımdan gelir. |
| K-064 | Yaşlandırma satır rengi: **yeşil = tamamen kapanmış**, **sarı = kısmen kapanmış**, **kırmızı = hiç kapanmamış**. | Yalnız rapor/görsel bilgidir; muhasebe kaydı üretmez. |
| K-065 | Kullanıcı erişimi **Master DB'de şirket + dönem bazında** kontrol edilir. Kullanıcı bir şirketin 2025 dönemine erişip 2026 dönemine erişemeyebilir. Period iş kayıtlarında Master `users` tablosuna gerçek FK kurulmaz; `user_id + user_name` snapshot tutulur. | Fiziksel DB izolasyonu ile yetki ayrılır; arşiv kayıtları isimle okunabilir. |
| K-066 | `print_profiles` **Master DB'dedir** ve şirket + kullanıcı + makine + çıktı tipi kapsamında çözülür. Etiket tasarımcısı için temel ölçüler `paper_code + width_mm + height_mm`; cihaz özel ayarlar gerekirse `settings` JSON'da tutulabilir. | Yıl değişiminde yazıcı profili tekrar kurulmaz; tasarımcı string ölçü parse etmez. |
| K-067 | v64 tarihsel/onaylı referans olarak korunur; kararlarla düzeltilmiş **v65 yeni kanonik UI referansıdır**. Kalite modülü kapsam dışıdır. | Eski sürüm değişmeden kalır, yeni kaynak tekilleşir. |
| K-068 | Belge iş tarihi alan adı **`document_date`**'tir. Dönem kilidi, rapor ve iş tarihi kontrolleri `created_at` veya genel `date` yerine bunu kullanır. | Tarih semantiği tekilleşir. |
| K-069 | Audit iki katmandır: Master işlemleri Master `activity_log`, period işlemleri ilgili period `activity_log`. | Arşiv period kendi işlem iziyle okunabilir. |
| K-070 | Şirketler arası kart kopyalamada hedefte **yeni ID** üretilir; `source_company_id + source_record_id` scalar provenance olarak tutulur, cross-DB FK değildir. Hedefte aynı kod varsa **kullanıcıya sorulur**: mevcut kartı kullan / yeni kod ver / iptal. Otomatik overwrite veya `-2` yoktur. | Şirketler arası kimlik çakışması ve sessiz veri ezme engellenir. |
| K-071 | Aynı şirketin dönem devrinde taşınan **bütün kartların ID ve kodları**, ayrıca taşınan **stok bakiye kayıtlarının ID'leri** korunur. Aktif kartlar + bakiye/hareket ilişkisi bulunan gerekli pasif kartlar taşınır; geçmiş hareketler taşınmaz. Sequence'ler `MAX(id)+1` seviyesine alınır. Devir sonunda önceki dönem kullanıcı/dönem yetkilerini seçerek yeni döneme kopyalama sorulur. | Yıllar arası kimlik/FK sürekliliği. |
| K-072 | **İrsaliye = fiziksel sevk + stok etkisi, cari etkisi yok. Fatura = finansal/cari borç etkisi.** İrsaliyeden faturada stok ikinci kez düşmez. İrsaliyesiz doğrudan fatura hem stok hem cari etkisi üretir. | Fiziksel sevk ve borçlandırma ayrılır. |
| K-073 | Satış satırı lokasyon taşıyabilir. Sipariş rezervasyonu yalnız mevcut kullanılabilir stok kadar yapılır; kalan açık kalır. Sistem depoları otomatik tarayıp aynı sipariş satırını birden fazla lokasyona rezervasyon dağıtım kayıtlarıyla bölebilir. Negatif stok izni negatif rezervasyon değildir. | Çok depolu toptancı sevki. |
| K-074 | Teklif revizyonları **ayrı immutable kayıtlar**dır; aynı ana teklif numarası altında `Rev.N` taşır ve `revision_no` saklanır. İç onay role sabit değil `sales.quote.approve` iznidir. | Eski revizyonlar kaybolmaz. |
| K-075 | Tahsilat için gereken minimum kasa/banka altyapısı Faz 3'e alınır: `cash_accounts`, `bank_accounts` ve tahsilat hareketleri. Virman/ekstre/mutabakat/çek-senet ayrıntıları Faz 5'te genişletilir. | Tahsilat gerçek hesaba bağlanabilir. |
| K-076 | Kısmi siparişte kullanılabilir kalan = `ordered - shipped - cancelled`. `cancelled_quantity` kadar miktar artık rezerve/sevk/fatura edilemez. Kısmi karşılanıp kalan iptal edilince sipariş `closed` olur. Bir irsaliye birden fazla faturaya bölünebilir; uygun aynı cari + para birimi + satış koşullarındaki birden fazla irsaliye tek faturada birleşebilir. | Gereksiz iptal belge/history yapısı yok. |
| K-077 | Satış satır fiyatı değiştirilebilir. Liste fiyatından **%20 ve üzeri mutlak sapmada uyarı + audit** oluşur; işlem bloke edilmez, `prices.override` zorunlu değildir. Maliyet altı satış uyarısı ayrıca devam eder. | K-051'i değiştirir. |
| K-078 | Resmî cari risk bakiyesi cari bakiyedir. Sipariş ekranı ayrıca **cari bakiye + bu sipariş + portföydeki henüz tahsil edilmemiş çek/senet riski** projeksiyonunu gösterir; limit aşımı uyarıdır, blok değildir. Diğer açık siparişler resmî bakiyeye katılmaz. | Açık hesap ve vadeli kıymet riski ayrı görünür. |
| K-079 | Kapanmış yıl varsayılan **salt okunur**; özel yeniden açma izni + gerekçe ile açılabilir. Kartlar fiziksel silinmez, `is_active=false` yapılır; taslak belge fiziksel silinebilir; posted belge silinmez, ters kayıtla düzeltilir. | Tarihsel kayıt korunur. |
| K-080 | Satır ve belge iskontosu **hem yüzde hem tutar** girilebilir; biri girildiğinde diğeri Money/BCMath ile hesaplanır, kesinleşmede ikisi de dondurulur. İskonto KDV'den öncedir. | Kullanıcı esnekliği + tekrar hesaplanabilirlik. |
| K-081 | Manuel düzeltme/mahsup için **Cari Borç/Alacak Fişi** vardır; gerekçe ve audit zorunludur. | Fatura dışı meşru cari hareketleri kontrollü tutulur. |
| K-082 | Çek/senet teslim alındığında/verildiğinde cari etkisi oluşur. Tahsil/ödeme aşamasında cari ikinci kez etkilenmez. Karşılıksız/geri dönen kıymet ters cari hareket üretir. Ciro, kıymeti veren müşteriyi ikinci kez etkilemeden karşı taraf carisini etkiler. | Toptancı çek/senet akışı bakiye sistemiyle uyumlu. |
| K-083 | Belge numara serileri period/yıl bazında yeniden başlayabilir; yıl/prefix numarada bulunabilir. Numara yalnız kesinleşmede, transaction içinde `lockForUpdate` ile üretilir. | Çakışmasız dönemsel seri. |
| K-084 | Çok dönemli rapor ilk sürümde period DB'leri ayrı sorgular ve PHP'de birleştirir. Period DB veri olarak Master olmadan okunabilir; uygulama login/yetki/print profile için Master ister. | FDW/dblink ilk sürümde yok. |
| K-085 | Kart kodu bir kez kullanıldıktan sonra pasifleşse bile başka karta tekrar verilmez. Aynı şirket dönem devrinde taşınan kimlik/kodlar korunur; şirketler arası kopyalamada hedef kimlik ayrı olabilir. | Kod tarihçesi bozulmaz. |


## 2026-10-02 — Faz 4 Alış kararları

| No | Karar | Gerekçe / teknik sonuç |
|---|---|---|
| K-086 | Faz 4 alış belge ailesi **esnektir**: satınalma talebi → tedarikçi teklifleri → satınalma siparişi → mal kabul/alış irsaliyesi → alış faturası belgeleri vardır; ara adımlar zorunlu değildir. | Kullanıcı ihtiyaca göre doğrudan sonraki uygun belgeyi oluşturabilir; kullanılmayan ara belge yapay olarak üretilmez. |
| K-087 | **Mal kabul/alış irsaliyesi operasyon kaydıdır; stok ve cari etkisi yoktur.** Stok girişi + tedarikçi cari credit etkisi yalnız alış faturası post edildiğinde oluşur. | Fiziksel kabul kaydı ile finansal/stok posting noktası ayrılır; kullanıcı tercihi gereği stok faturaya kadar artmaz. |
| K-088 | Alışta **esnek kısmi akış** vardır: sipariş kısmi teslim alınabilir, kalan açık kalabilir veya iptal edilebilir; bir mal kabul birden fazla faturaya bölünebilir, uyumlu birden fazla mal kabul tek faturada birleşebilir. | Kısmi tedarik ve toplu fatura senaryoları desteklenir; miktar kaynağı satır ilişkileridir. |
| K-089 | **Tedarikçi ödeme akışı Faz 5'tedir.** Faz 4 alış faturası borcu oluşturur; Faz 4'te kasa/banka ödeme posting eylemi yoktur. | Kasa/Banka/Çek-Senet kapsamı Faz 5'te tek yerde kalır. |
| K-090 | Satınalma teklif toplamada **belge bazlı ve satır bazlı karşılaştırma birlikte desteklenir**; sistem otomatik kazanan/en ucuz teklif seçmez. | Kullanıcı ister tek teklifi, ister satır bazında farklı tedarikçileri seçebilir. |
| K-091 | Tedarikçi teklif seçimi için **ayrı approval state veya tutar eşiği yoktur**. Yetkili kullanıcı `purchasing.quote.select` izniyle seçimi doğrudan yapar; seçim period audit'e yazılır. | Basit teklif toplama kapsamı korunur; gereksiz onay katmanı eklenmez. |


## 2026-10-02 — Faz 5 Kasa/Banka/Çek-Senet kararları

| No | Karar | Gerekçe / teknik sonuç |
|---|---|---|
| K-092 | Virman **Kasa→Kasa, Kasa→Banka, Banka→Kasa ve Banka→Banka** destekler; kaynak ve hedef hesap aynı para biriminde olmalıdır. | Faz 5 virmanı döviz dönüşümü/kur farkı üretmez; kaynak ve hedef aynı hesap olamaz. |
| K-093 | Tedarikçi ödeme ana işlemi genel ödeme formundadır; alış faturasında **Ödeme Yap** kısayolu vardır. Kaynak fatura ilişkisi bilgi amaçlıdır; settlement zorunlu değildir ve kısmi ödeme serbesttir. | K-062 açık hesap modelini korur, kullanıcıya pratik fatura kısayolu sağlar. |
| K-094 | Kasa sayımı toplam fiili bakiye üzerinden yapılır; kupür sayımı yoktur. Fark kullanıcıya gösterilir; gerekçeyle onaylanırsa ayrı kasa sayım farkı hareketi oluşur. | Geçmiş hareketler mutate edilmeden fiziksel sayım farkı kayıt altına alınır. |
| K-095 | İlk Faz 5 sürümünde banka ekstresi dosya importu ve otomatik eşleştirme yoktur. Banka hareketleri manuel olarak **mutabık / mutabık değil** işaretlenir. | İlk sürüm kapsamı kontrollü tutulur; mutabakat finans hareketi üretmez. |
| K-096 | Çek/senet tam kontrollü yaşam döngüsüne sahiptir. Alınan kıymet: alındı→portföyde→ciro/tahsile verildi→tahsil edildi veya karşılıksız/geri döndü. Verilen kıymet: verildi→ödeme bekliyor→ödendi veya geri döndü/iptal. | K-082 cari etki kuralları lifecycle ile somutlaştırılır. |
| K-097 | Çek/senet geniş veri seti + banka operasyon alanları taşır; ciroda karşı cari zorunludur ve history korunur. | Banka teslimi, tahsil/ödeme referansı, protesto/karşılıksız detayları ve operasyon metadata ilk sürüm veri modelinde desteklenir. |


## 2026-10-02 — Faz 6 İade kararları

| No | Karar | Gerekçe / teknik sonuç |
|---|---|---|
| K-098 | Faz 6 hem **satış iadesi** hem **alış iadesi** kapsar. | İade davranışı iki ticari yönde tek fazda tamamlanır. |
| K-099 | İade kaynak belgeye bağlı olabilir ama **zorunlu değildir**. Kaynaklı ve kaynaksız iade desteklenir. | Canlı geçiş öncesi eski/dış sistem işlemleri de iade edilebilir; kaynaksız iadede kontrollü manuel kurallar uygulanır. |
| K-100 | Satış iadesi post edildiğinde **müşteri cari credit + fiziksel stok in + aynı miktar quarantine** birlikte oluşur. | Mal fiziksel olarak geri gelir fakat kontrol tamamlanana kadar kullanılabilir stok artmaz. |
| K-101 | Alış iadesi post edildiğinde **stok out + tedarikçi cari debit** aynı transaction içinde oluşur. | Tedarikçiye iade fiziksel ve cari etkiyi birlikte işler. |
| K-102 | Karantina kararı **kısmi** olabilir; aynı iade miktarı satılabilir/hurda/bekleyen parçalara ayrılabilir. | Gerçek kontrol sonucu parçalı olabilir; kaynak miktar korunur. |
| K-103 | Kaynaklı satış iadesi stok maliyeti **orijinal satış stok çıkış unit_cost snapshot'ı** ile geri alınır. | İade, satılan malın çıktığı maliyetle geri girer; satılabilir kararı ikinci maliyet hareketi üretmez. |
| K-104 | Alış iadesinde maliyet temeli satır bazında kullanıcı tarafından seçilir: **mevcut moving average** veya **kaynak alış faturası maliyeti**. Kaynaksız iadede yalnız moving average seçilebilir. | Lot/parti yoktur; kullanıcı ticari bağlama göre iki kanonik maliyet temelinden birini seçer. |
| K-105 | İade belgesi **otomatik para hareketi üretmez**. Müşteri geri ödemesi veya tedarikçiden para tahsilatı ayrı finans işlemi olarak yapılır. | Cari etki ile gerçek kasa/banka hareketi ayrılır; settlement zorunlu değildir. |
| K-106 | Aynı kaynak fatura satırı **birden fazla kısmi iade** alabilir; etkin toplam iade kaynak miktarı aşamaz. Reverse edilen iadeler kullanılabilir iade miktarını geri açar. | Faz 3/4 source-line partial modeli iadelere de uygulanır. |
| K-107 | Kaynaklı iadede fiyat, iskonto, KDV, birim ve conversion snapshot **orijinal frozen değerlerden** alınır; güncel kart/fiyat kullanılmaz. | Tarihsel belge değeri korunur ve yeniden fiyatlama yapılmaz. |
| K-108 | Dövizli alış iadesinde **orijinal alış faturasının frozen kuru** kullanılır; kur farkı hesabı yapılmaz. | K-013 ile uyumlu; iade yeni FX değerleme açmaz. |
| K-109 | Önceki dönem/yıl belgesi **mevcut açık dönemde** iade edilebilir. Eski period DB değiştirilmez; cross-DB FK kurulmaz; kaynak period/document/line kimliği ve gerekli frozen snapshot iade üzerinde tutulur. | Kapalı dönem korunur, geçmiş satış/alım için yeni dönemde gerçek ticari iade yapılabilir. |
| K-110 | Kaynaksız iadede kontrollü manuel giriş vardır: cari + ürün + miktar zorunlu, fiyat/KDV manuel, gerekçe zorunlu, ayrı izin + audit. Kaynaksız satış iadesi stok maliyeti mevcut moving average'dır. | Kaynak belge yokken sessiz varsayım yerine açık ve denetlenebilir manuel işlem kullanılır. |
| K-111 | İade için ayrı approval state yoktur; yetkili kullanıcı **doğrudan post eder**. | Faz 4 seçim yaklaşımıyla tutarlı, gereksiz onay katmanı yoktur. |
| K-112 | İade nedeni **zorunlu kontrollü neden + açıklama** modelidir; neden/açıklama audit edilir. Neden kod listesi Faz 6 dokümantasyonunda ayrı kanonik liste olarak tanımlanmalıdır. | Raporlanabilir neden gerekir; serbest metin tek başına yeterli değildir. |

## Açık kararlar

**Yok.** Faz 6 başlangıç kararları K-098…K-112 ile kilitlendi.
