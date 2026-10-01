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
| K-056 | `company_copy_permissions` kalktı; izin master'da `company_copy_permissions` | Kopyalama dönem veritabanları arasında |
| K-057 | Pazaryeri stoğu **satış anında anlık** gönderilir; 15 dk tarama yedek | Çift satış riski (A-001) |
| K-058 | Ürün bazında `channel_stock_mode`: stok / üretim / elle | Bazı ürünler üretimden karşılanıyor |
| K-059 | **Konfigüratör fiyatı etkilemez**; yalnız ürün özelliği tanımlar | A-002 |
| K-060 | Reçetede fire yüzdesi **yok**, çıkışta elle girilir · Konsolide rapor **ayrı izinle** · Koli etiketi **ambar fişine** bağlı · Pazaryerleri **varyantsız** · **Kalite modülü kapsam dışı** · Satınalma talebi + teklif toplama **basit haliyle eklendi** | A-003, A-004, A-006, A-007 |
| K-061 | Etiket yazdırma **marka/model bağımsızdır**; ZPL şablonu genel, etiket ölçüsü `print_profiles.paper_size` ayarından gelir; `printer_name` yalnız fiziksel cihaz eşlemesidir | A-005 kapatıldı; farklı marka ve ölçüler desteklenir |
| K-048 | **Stok hareketi her zaman ürünün temel biriminde**; belge satırı `base_quantity` ve dondurulmuş `conversion_factor` saklar | Birim karışırsa stok katlanır |
| K-049 | Dönüşüm tanımlı değilse işlem **engellenir**; katsayı 1 varsayılmaz | Sessiz yanlış stoktan iyidir |
| K-050 | Fiyat çözümleme: cari listesi → varsayılan liste → `products.list_price` → 0 | Tanımsızdı |
| K-051 | Satır fiyatı listeden %20+ saparsa uyarı; `prices.override` izni yoksa değiştirilemez | — |
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

## Açık kararlar

**Yok.** A-001…A-008 kapatıldı. A-005 kararı K-061 olarak kaydedildi.
