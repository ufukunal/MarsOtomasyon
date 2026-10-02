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

## 2026-10-02 — Faz 6 ek karar

| No | Karar | Gerekçe / teknik sonuç |
|---|---|---|
| K-113 | Satış ve alış iadeleri aynı sabit neden kodlarını kullanır: `wrong_product`, `damaged`, `defective`, `quantity_error`, `customer_request`, `supplier_return`, `other`. | İlk sürümde ayrı return reason kartı/CRUD kurulmaz; neden raporlanabilir ve audit edilebilir kalır. |


## 2026-10-02 — Faz 7 İthalat kararları

| No | Karar | Gerekçe / teknik sonuç |
|---|---|---|
| K-114 | İthalat ürünleri alış faturasında mevcut Faz 4 kurallarıyla ilk maliyetle stoğa girer; ithalat dosyası finalize edildiğinde ek ithalat masrafları **miktarı değiştirmeyen maliyet düzeltmesi** olarak moving average'a yansır. | Faz 4 purchase invoice posting modeli korunur; sonradan gelen ithalat maliyetleri fiziksel stoğu ikinci kez artırmaz. |
| K-115 | Masraf dağıtım yöntemleri ilk sürümde **alış değerine göre, miktara göre veya manuel** seçimidir. | Ağırlık/hacim için ürün kartı genişletmesi ilk sürüme alınmaz. |
| K-116 | İthalat masraf türleri sabit temel liste + `other` modelidir: freight, customs_duty, insurance, storage, customs_brokerage, port_terminal, other. | İlk sürümde import expense type CRUD yoktur; `other` açıklama gerektirir. |
| K-117 | İthalat masrafları hem mevcut **purchase_invoice** belgelerinden hem de cari etkisi olmayan manuel import expense kayıtlarından gelebilir. | Faturalı masraf mevcut alış/cari/ödeme altyapısını yeniden kullanır; faturasız/vergi-harç tipi maliyetler ayrıca tutulur. |
| K-118 | Stok maliyetine gümrük vergisi ve doğrudan ithalat giderleri dahil edilir; **indirilebilir ithalat KDV'si maliyete dahil edilmez**. Geri alınamayan vergi/harç maliyet masrafı olarak girilebilir. | Stok maliyeti ile indirilebilir vergi ayrılır; genel muhasebe kapsamı genişletilmez. |
| K-119 | Aynı ithalat dosyasında farklı para birimli kaynak belgeler bulunabilir; her belge kendi frozen kuruyla şirket temel para birimine çevrilir ve maliyet havuzunda birleştirilir. | K-013 frozen kur yaklaşımı korunur; yeni kur farkı hesabı yoktur. |
| K-120 | Purchase invoice kaynakları ithalat dosyasına **satır bazında tam** bağlanır; aynı invoice line birden fazla import dosyasına miktar bazında bölünmez. | Lot/parti ve miktar bazlı import parçalama karmaşıklığı ilk sürüme alınmaz. |
| K-121 | Finalize edilmiş ithalat dosyası immutable'dır; yerinde tekrar açılmaz/değiştirilmez. | K-016 kesinleşmiş kayıt ilkesi korunur. |
| K-122 | Finalize sonrası gelen masraf, eski dosyayı açmak yerine ayrı **import cost adjustment** kaydıyla aynı ürünlere ek maliyet olarak dağıtılır. | Geçmiş maliyet hesapları değişmeden yeni maliyet farkı izlenebilir olur. |
| K-123 | Miktar değiştirmeyen ithalat maliyet düzeltmelerinin gerçek kaynağı ayrı **inventory_cost_adjustments** tablosudur; zero-quantity stock movement kullanılmaz ve product_costs doğrudan kaynak olmadan güncellenmez. | Fiziksel stok hareketi ile maliyet değer hareketi ayrılır; audit/integrity korunur. |
| K-124 | `product_costs.import_cost`, ürünün **son finalize edilen ithalat birim maliyeti** snapshot'ıdır; bilgi amaçlıdır. Geçerli maliyet yine `moving_average`dır. | Mevcut product_costs alanı kanonik anlam kazanır. |
| K-125 | İthalat dosyasının kendi cari etkisi yoktur. Cari etkiler kaynak purchase_invoice belgelerinde oluşur; manuel import expense cari hareket üretmez. | Çift borç/cari etkisi engellenir. |
| K-126 | İthalat dosyası yaşam döngüsü **draft → cost_collection → finalized → adjusted** modelidir. Ayrı approval state yoktur. | Masraf toplama/finalize ayrılır; sonradan adjustment görünür olur. |
| K-127 | Aynı ithalat dosyası birden fazla purchase invoice ve farklı supplier kaynaklarını içerebilir; ortak şart aynı fiziksel ithalat operasyonuna ait olmalarıdır. | İthalat operasyonu tek fatura kimliğiyle sınırlandırılmaz. |
| K-128 | Masraf dağıtımındaki yuvarlama farkı deterministik olarak son uygun satıra verilir; toplam dağıtılan tutar masraf toplamına tam eşitlenir. | Dağıtılmamış maliyet bırakılmaz. |
| K-129 | Faz 7 ilk sürümünde ağırlık/hacim bazlı dağıtım yoktur; ürün kartına bunun için yeni alan eklenmez. | Faz kapsamı korunur; K-115 yöntemleri yeterlidir. |

## 2026-10-02 — Faz 7 ek karar

| No | Karar | Gerekçe / teknik sonuç |
|---|---|---|
| K-130 | İthalat ek maliyeti kaynak ithalat miktarına bölünür: `unit_adjustment = additional_cost / original_import_quantity`. Bu birim fark current `moving_average` değerine eklenir. Geçmiş satış maliyetleri geriye dönük değiştirilmez. Mevcut stok quantity sıfır/negatif olsa bile moving_average snapshot bu birim farkla güncellenebilir; fiziksel stok miktarı değişmez. | Lot/parti takibi olmadığı için kalan stok oranı güvenilir biçimde izlenemez. Kaynak ithalat miktarı deterministik maliyet tabanı sağlar. |


## 2026-10-02 — Faz 8 Basit üretim/fason kararları

| No | Karar | Gerekçe / teknik sonuç |
|---|---|---|
| K-131 | Faz 8 **basit iç üretim + fason** birlikte kapsar. | Reçete, üretim emri, hammadde/mamul hareketleri ve fason akışı aynı fazda tamamlanır. |
| K-132 | Bir mamulde **tek aktif reçete** vardır; yeni değişiklik ayrı immutable revizyon olarak açılır. | Geçmiş üretimlerin reçete geçmişi korunur. |
| K-133 | Reçete revizyonları `Rev.N` immutable kayıtlardır; yeni üretimler varsayılan son aktif revizyonu kullanır. | Mutable reçete geçmişi bozulmaz. |
| K-134 | Reçete kendi **output_quantity** tabanını taşır; component miktarları bu çıktıya göre tanımlanır ve üretim miktarına oransal ölçeklenir. | “100 adet için 4 kg” gibi reçeteler doğal desteklenir. |
| K-135 | Üretim emri açılırken reçete revizyonu, component listesi, miktarlar ve unit/conversion değerleri snapshot edilir. | Sonraki reçete değişiklikleri açık üretim emrini değiştirmez. |
| K-136 | Reçete planned consumption önerir; kullanıcı actual component consumption miktarını değiştirebilir ve fark audit edilir. | Basit üretimde gerçekleşen tüketim reçeteden sapabilir. |
| K-137 | Fire component bazında actual miktar olarak girilir; normal consumption + fire stoktan çıkar ve fire maliyeti mamul maliyetine dahil edilir. | K-060 fire yüzdesi yok kararını somutlaştırır. |
| K-138 | Hammadde source location component satır bazında seçilir; farklı component'ler farklı depolardan tüketilebilir. | Üretim emri tek source depoya zorlanmaz. |
| K-139 | Üretim sonucu **birden fazla target location'a bölünebilir**. | Kullanıcı A-062 seçenek 2'yi seçti; completion output satırları lokasyon bazında dağıtılabilir. |
| K-140 | Component stock çıkışı ürünün mevcut `allow_negative_stock` kuralına uyar. | Üretim için ikinci negatif stok politikası kurulmaz. |
| K-141 | Üretim emri kısmi tamamlanabilir; aynı emirden birden fazla completion yapılabilir ve kalan iptal edilebilir. | Gerçek üretim parça parça sonuçlanabilir. |
| K-142 | Üretim emri yaşam döngüsü `draft → confirmed → in_progress → completed/cancelled` modelidir; ayrı approval state yoktur. | Basit üretim kapsamı korunur. |
| K-143 | Hammadde tüketimi ve mamul stock-in **production completion anında aynı transaction** içinde oluşur. | Ayrı zorunlu material issue süreci kurulmaz. |
| K-144 | Mamul üretim maliyeti, actual consumed component hareketlerinin moving-average snapshot maliyetlerinden hesaplanır; fire dahil edilir. | Plan maliyeti değil gerçekleşen maliyet kullanılır. |
| K-145 | İlk Faz 8 sürümünde iç üretim için işçilik/enerji/overhead ek maliyet satırları yoktur; mamul maliyeti yalnız actual material consumption'dan oluşur. | Basit üretim sınırı korunur. |
| K-146 | Production stock-in calculated production unit cost ile moving average'ı günceller; `product_costs.production_cost` son production unit cost snapshot'ıdır. | K-006 moving average sistemi korunur. |
| K-147 | Fason temel modelinde malzeme şirketten çıkar; fasoncu yalnız işçilik/hizmet sağlar. | İlk sürümde fasoncunun kendi malzemesiyle karma üretim yoktur. |
| K-148 | Fasoncu stoğu ayrı tablo yerine **subcontractor location** olarak izlenir; kendi depodan fason location'a transfer edilir ve mülkiyet şirkette kalır. | Mevcut location/transfer altyapısı yeniden kullanılır. |
| K-149 | Subcontractor location normal satış rezervasyon/sevki için kullanılamaz; yalnız fason transfer/üretim akışına açıktır. | Fason stoğu normal kullanılabilir stok gibi satılmaz. |
| K-150 | Fasoncu normal supplier/contact kartıdır ve bir subcontractor location ile ilişkilendirilir; ayrı subcontractor master kartı yoktur. | Kart yapısı gereksiz genişlemez. |
| K-151 | Fason hizmet bedeli normal `purchase_invoice` üzerinden kaydedilir ve production order'a bilgi/maliyet ilişkisiyle bağlanır. | Faz 4 alış/cari/ödeme altyapısı yeniden kullanılır. |
| K-152 | Fason hizmet bedeli mamul maliyetine dahil edilir. | Gerçek fason üretim maliyeti material + service cost olur. |
| K-153 | Fason completion, hizmet faturası gelmeden yapılabilir; sonradan gelen hizmet faturası ayrı production cost adjustment oluşturur. | Operasyon, faturayı beklemek zorunda kalmaz. |
| K-154 | Sonradan gelen fason maliyet için Faz 7 `inventory_cost_adjustments` altyapısı yeniden kullanılır; reason=`subcontract_late_cost`. | Miktarı değiştirmeyen maliyet düzeltmesi tek kanonik altyapıda kalır. |
| K-155 | Fasona mal gönderimi kısmi olabilir. | Planlanan miktar tek sevkte gönderilmek zorunda değildir. |
| K-156 | Fason dönüş/completion kısmi olabilir; kalan component stok fason location'da bekleyebilir. | Parçalı fason teslim doğal desteklenir. |
| K-157 | Fason fire iç üretimle aynı component-level actual fire kuralını kullanır ve mamul maliyetine dahil edilir. | İki üretim tipinde fire semantiği tekleşir. |
| K-158 | `channel_stock_mode=production` satış siparişi confirmed olduğunda ihtiyaç kadar **draft production order** otomatik açılır; kullanıcı confirm eder. | Otomasyon vardır fakat insan kontrolü korunur. |
| K-159 | Sales order → production order ilişkisi opsiyoneldir; bağımsız production order da açılabilir. | Üretim yalnız siparişe bağlı değildir. |
| K-160 | Aktif reçeteler/revizyonlar dönem devrinde ürün ilişkileri korunarak taşınır; geçmiş production order'lar taşınmaz. | Kart/snapshot sürekliliği korunur. |
| K-161 | Açık production/subcontract order yeni döneme taşınmaz; dönem kapanmadan tamamlanır/iptal edilir. Fason location'daki fiziksel stok location bazında açılış stoklarına taşınır. | Belge geçmişi taşınmazken fiziksel stok kaybolmaz. |
| K-162 | Production completion reverse edilebilir; yeni ters kayıt mamul stock-out + component stock-in ve ilgili maliyet ters etkilerini üretir, original immutable kalır. | K-016 ters kayıt ilkesi üretime uygulanır. |

## 2026-10-03 — Faz 9 E-ticaret kararları

| No | Karar | Gerekçe / teknik sonuç |
|---|---|---|
| K-163 | Faz 9 ilk sürüm kanalları Trendyol, Hepsiburada, N11 ve WooCommerce'dir; ortak adapter sözleşmesi kullanılır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-164 | Kanal credential/account bağlantıları Master DB'de şirket bazında tutulur; period DB yalnız dönemsel mapping/order/sync verisini tutar. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-165 | Aynı platformdan bir şirkette birden fazla kanal hesabı/mağaza olabilir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-166 | Ürün-kanal eşlemesi açık mapping tablosuyla tutulur; external listing/product id ve external sku saklanır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-167 | Pazaryerinde her internal varyant ayrı listing'dir; variant group gönderilmez. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-168 | Sistem hem yeni listing yayınlayabilir hem mevcut external listing'e bağlanabilir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-169 | Internal product temel kaynaktır; kanal listing üzerinde başlık/açıklama/fiyat/görsel/teslim süresi gibi override alanları olabilir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-170 | Kanal görsel seti yoksa Ortak görsel setine fallback yapılır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-171 | Kanal bazında fiyat override desteklenir; yoksa mevcut fiyat çözümleme zincirine düşülür. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-172 | Faz 9 satış kanalları ilk sürümde TRY kullanır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-173 | Product channel_stock_mode varsayılandır; channel listing bazında stock|production|manual override yapılabilir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-174 | stock modunda gönderilecek miktar, channel listing'e atanmış satışa uygun location kapsamının kullanılabilir stok toplamından hesaplanır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-175 | Channel-product bazında max_channel_quantity ve withhold_quantity birlikte desteklenir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-176 | production modunda fixed quantity ve teslim süresi channel-product bazındadır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-177 | manual modunda quantity channel-product bazında elle tutulur; fiziksel stok değişikliklerinden otomatik etkilenmez. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-178 | Set ürün stock modunda, seçili kanal location kapsamındaki component available stoklarından min(component/required) ile hesaplanır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-179 | Kanal siparişi mevcut sales_order belgesine import edilir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-180 | Geçerli external sipariş idempotent şekilde otomatik confirmed sales_order oluşturur. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-181 | Idempotency anahtarı channel_account + external_order_id kombinasyonudur. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-182 | Her kanal hesabı için tek marketplace customer contact kullanılır; gerçek buyer/shipping bilgileri order snapshot'ında tutulur. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-183 | Kanal siparişi importunda collection/cash-bank movement üretilmez. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-184 | Marketplace payout/commission mutabakatı ilk Faz 9 kapsamı dışındadır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-185 | External sipariş satır fiyatı platformdan frozen snapshot olarak alınır; internal fiyatla yeniden hesaplanmaz. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-186 | Platformdan gelen satır/belge indirimleri normalize edilip frozen snapshot olarak saklanır; kampanya metadata korunabilir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-187 | Buyer/recipient/phone/email/address/city/district/postcode/cargo/package bilgileri order shipping snapshot'ında tutulur. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-188 | İlk sürümde ayrı kargo firması API entegrasyonu yoktur; marketplace shipment/cargo bilgisi okunur ve mevcut sevk akışı kullanılır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-189 | External cancel henüz fulfillment yapılmamış miktarda mevcut sales_order kalan iptal mantığını uygular; fulfillment sonrası mal dönüşü Faz 6 iadedir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-190 | Marketplace return event'i draft sales_return oluşturur; kullanıcı kontrol/post eder, Faz 6 quarantine kuralı aynen geçerlidir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-191 | Desteklenen kanallarda shipment/cargo durumu adapter üzerinden platforma push edilir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-192 | Ürün/stok/fiyat yönü sistem→kanal; sipariş/cancel/return yönü kanal→sistemdir; dış ürün değişikliği internal product'u mutate etmez. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-193 | Kanal API hatalarında 3 retry (30/60/120 sn), persistent channel_sync_errors ve manuel retry vardır; duplicate pending işler coalesce edilir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-194 | Webhook destekleyen kanalda webhook birincil, polling güvenlik ağıdır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-195 | Inbound order/cancel/return polling 15 dakikada bir ve manuel tetiklemelidir. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-196 | Channel account Master'da kalır; period channel-product mapping yeni döneme ürün ID sürekliliğiyle taşınır, eski order/sync history taşınmaz. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-197 | External order duplicate engeli dönemler arası korunur; event hangi açık period'a aitse oraya import edilir ve tekrar çekim ikinci order oluşturmaz. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-198 | Listing pasifleştirmede mapping silinmez; is_active=false olur ve kanal listing pasif/stock=0 yapılır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-199 | İlk sürüm kanal kategori/özellik değerleri listing metadata'sında manuel tutulur; otomatik category matching yoktur. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-200 | Kanal başlık/açıklama override opsiyoneldir; yoksa internal product name/description kullanılır. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |
| K-201 | Her dış API işlemi için kalıcı sync event/history tutulur; yalnız gerekli güvenli metadata/hash saklanır, hassas tam payload tutulmaz. | Faz 9 kapsamı ve entegrasyon davranışı kanonikleşir. |

## 2026-10-03 — Faz 10 Raporlar / çıktılar / tasarımcı kararları

| No | Karar | Gerekçe / teknik sonuç |
|---|---|---|
| K-202 | Faz 10 en geniş rapor/çıktı/tasarım kapsamıdır: operasyonel raporlar, dashboard, export, çok dönemli rapor, belge şablonları, etiket/koli etiketi ve yazdırma altyapısı birlikte tamamlanır. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-203 | Raporlar mevcut kanonik işlem tablolarını okur; ayrı rapor bakiye/gerçek kaynak tabloları oluşturulmaz. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-204 | Rapor motoru tek ReportDefinition/ReportQuery sözleşmesi kullanır; filtre, kolon, sıralama, toplam ve permission metadata tanımlıdır. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-205 | Rapor erişimi permission tabanlıdır; maliyet/kâr kolonları cost.view olmadan query/select seviyesinde üretilmez. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-206 | Konsolide çok dönemli rapor reports.consolidated izni ister; period DB'ler ayrı sorgulanır ve PHP'de birleştirilir, FDW/dblink zorunlu değildir. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-207 | Rapor tarih filtresi iş tarihidir ve belge/hareket türüne göre document_date/transaction_date/movement_date gibi kanonik alanı kullanır; created_at iş tarihi değildir. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-208 | Rapor çıktıları ekran, PDF, XLSX ve CSV destekler; PDF Browsershot üzerinden, tablo exportları aynı rapor query sonucundan üretilir. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-209 | Büyük rapor/export işi queue üzerinden üretilebilir; kullanıcıya durum/progress/download kaydı gösterilir, session PeriodContext'e güvenilmez. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-210 | Rapor filtre presetleri kullanıcı+şirket bazında kaydedilebilir; paylaşılmış preset opsiyonel şirket kapsamındadır. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-211 | Dashboard kartları mevcut rapor/query servislerini yeniden kullanır; ayrı dashboard bakiye tablosu gerçek kaynak olmaz. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-212 | Rapor cache yalnız ağır tarihsel/immutable sonuçlarda kullanılabilir; anahtar şirket+dönem+filtre+permission kapsamı taşır. Anlık stok/cari bakiye cache edilmez. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-213 | Belge tasarımcısı bölüm tabanlıdır; serbest sürükle-bırak canvas yoktur. Header/body/table/footer/summary/signature/notes gibi sıralı bölümler yapılandırılır. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-214 | Belge şablonları Master DB'de şirket bazında tutulur ve dönemler boyunca kullanılabilir; şablon revizyonları immutable Rev.N olarak saklanır. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-215 | Her çıktı tipi için bir varsayılan aktif template olabilir; kullanıcı/print_profile template override yapabilir. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-216 | Template veri alanları allow-list token registry üzerinden çözülür; kullanıcı serbest PHP/SQL/Blade kodu yazamaz. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-217 | Belge template snapshot/revision id kesinleşmiş belgenin çıktı provenance'ında saklanabilir; tekrar baskıda seçilen davranış açıkça 'güncel template' veya 'orijinal revision' olarak kullanıcıya sunulur. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-218 | PDF/A4 çıktı HTML+CSS şablonundan Browsershot ile üretilir; uygulama yazıcıya doğrudan konuşmaz, tek giriş PrintManager'dır. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-219 | Etiket tasarımcısı bölüm/alan tabanlıdır; paper_code+width_mm+height_mm kullanır, ZPL/PDF taşıyıcısı PrintManager/driver arkasındadır. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-220 | Ürün etiketi barkod, ürün kodu/adı, fiyat, birim ve izinli ürün alanlarını kullanabilir; hangi alanların basılacağı template tanımıdır. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-221 | Koli etiketi K-060 gereği ambar fişi/sevk kaynağına bağlıdır; bağımsız koli stok kaydı oluşturmaz. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-222 | Yazdırma profili çözüm sırası company+user+machine+type → company+user+type → company+type → system default olarak korunur. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-223 | Toplu yazdırma desteklenir; her job seçilen template/profile snapshot metadata ve sonuç durumunu audit/history ile taşır. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-224 | Rapor/export/print geçmişi hassas tam belge verisini çoğaltmaz; dosya referansı, parametre özeti/hash, actor, tarih ve sonuç metadata tutulur. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-225 | Rapor kataloğu en az satış, alış, cari, stok, finans, çek/senet, iade/karantina, ithalat, üretim/fason ve e-ticaret alanlarını kapsar. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-226 | Satış raporlarında ciro, miktar, iskonto, KDV, maliyet, brüt kâr ve marj; maliyet/kâr alanları cost.view ile korunur. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-227 | Stok raporlarında mevcut/available/reserved/quarantine/consignment, lokasyon dağılımı, hareket dökümü, hareketli ortalama ve stok değerleme bulunur. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-228 | Cari/finans raporlarında ekstre, bakiye, FIFO yaşlandırma, risk, kasa/banka hareketleri, çek/senet portföy/ödenecek ve vade görünümü bulunur. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-229 | İthalat/üretim/e-ticaret raporları import cost allocation, production consumption/fire/cost, fason stok/hizmet, channel listing/sync/order performansını kapsar. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-230 | Faz 10 raporları genel muhasebe, resmi mali tablo, vergi beyannamesi veya GİB/e-belge raporu üretmez; K-002 kapsamı korunur. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-231 | Rapor kolonları kullanıcı tarafından görünürlük/sıra bazında kişiselleştirilebilir; veri kaynağı veya formül keyfi değiştirilemez. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-232 | Raporlar drill-down ile kaynak kart/belge/harekete gidebilir; drill-down yeni veri üretmez ve mevcut permission kontrollerine uyar. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-233 | Export edilen para/miktar değerleri formatlanmış metin yerine mümkün olan yerde gerçek numeric hücre olarak yazılır; Money hesabı yine BCMath tabanlıdır. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-234 | PDF/print çıktısında locale, tarih/para biçimi ve şirket kimliği template render context'inden gelir; iş hesapları render katmanında yeniden hesaplanmaz. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |
| K-235 | Template ve report definition değişiklikleri version optimistic lock + activity log ile korunur; finalized template revision yerinde mutate edilmez. | Kullanıcının en geniş Faz 10 kapsam talebi ve mevcut mimari kilitlerle uyumlu. |

## Açık kararlar

**Yok.** Faz 10 kapsamı K-202…K-235 ile kilitlendi.
