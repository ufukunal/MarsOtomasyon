# MarsOtomasyon — Güncelleme Promptu

Bu dosya `DEVIR-PROMPT.md`den sonra okunur. Çelişkide bu dosya ve karar günlüğü üstündür.

## 01.10.2026 kanonik güncelleme

- Kartlar dahil yıllık işletme verisi period DB'dedir; period tablolarında `company_id`/global scope yoktur.
- Master: şirket/dönem/kullanıcı/yetki/şirket+dönem erişimi/kur/ayar/`company_copy_permissions`/`print_profiles`/master audit.
- `print_profiles` Master'da şirket+kullanıcı+makine+çıktı tipi kapsamındadır; etiket ölçüsü `paper_code + width_mm + height_mm`.
- Period kullanıcı alanlarında Master user'a gerçek FK yoktur; `user_id + user_name` snapshot vardır.
- Aynı şirket dönem devrinde kart ve taşınan stok bakiye ID/kodları korunur; devir sonu kullanıcı dönem yetkisi kopyalama sorulur.
- Şirketler arası kopyalamada hedef yeni ID üretir; kaynak `source_company_id + source_record_id` ile izlenir; kod çakışmasında kullanıcı karar verir.
- `document_date` tek iş tarihi alanıdır.
- İrsaliye sevk+stok, fatura cari etkisidir; irsaliyeden faturada stok ikinci kez düşmez; doğrudan fatura stok+cari yapar.
- Cari bakiye `contact_transactions` toplamıdır; zorunlu fatura tahsilat eşleştirmesi yoktur.
- Yaşlandırma bilgilendirme amaçlı FIFO'dur; yeşil=tam kapanmış, sarı=kısmi, kırmızı=hiç kapanmamış.
- Çek/senet tesliminde cari etkisi olur; tahsil/ödeme ikinci kez cari etkilemez; karşılıksız/geri dönüş ters hareket üretir.
- Portföydeki henüz tahsil edilmemiş kıymetler ticari risk olarak ayrıca gösterilir.
- Satış fiyatı %20+ sapmada uyarı+audit; blok ve `prices.override` zorunluluğu yoktur.
- Satır ve belge iskontosu yüzde veya tutar girilebilir, ikisi de kesinleşmede dondurulur.
- Faz 3'e minimum kasa/banka altyapısı alınır.
- Faz 3 iş kuralı dosya numaraları mevcut 11–27 ile çakışmamak için **28–31** kullanılacaktır.
- Faz 0–2 görev numaraları korunur ve görevler yeni standalone standarda yükseltilir.
- v64 korunur; **v65 güncel UI referansıdır.**
- Faz 4 Alış kararları K-086…K-091 ile kilitlendi; açık A kararı yoktur.

Repo tutarlılık temizliği ve Faz 0–2 görev revizyonu tamamlandı. **Faz 3 Satış, Faz 4 Alış, Faz 5 Finans, Faz 6 İade, Faz 7 İthalat ve Faz 8 Basit üretim/fason dokümantasyonları yazıldı. Faz 8 K-131…K-162 kilitli; veri modeli 41, iş kuralları 45–47, üretim/fason ekranları ve G-800…G-809 hazırdır. Faz 9 kullanıcı onayı olmadan başlatılmaz.**


## 02.10.2026 Faz 4 Alış güncellemesi

- K-086: esnek alış belge zinciri.
- K-087: goods_receipt operasyonel; stok+cari+maliyet yalnız purchase_invoice posting'inde.
- K-088: kısmi teslim ve kısmi faturalama esnek.
- K-089: tedarikçi ödemesi Faz 5.
- K-090: teklif karşılaştırma belge + satır bazlı; otomatik kazanan yok.
- K-091: teklif seçiminde ayrı approval/eşik yok; izin tabanlı kullanıcı seçimi.
- Dövizli alışta belge currency/exchange_rate snapshot kalır; stok maliyeti ve cari ledger frozen kurla şirket temel para birimine çevrilir.
- Faz 4'ün tek yeni seçim tablosu `purchase_quote_selections`; ortak belge/stok/cari çekirdeği yeniden kullanılmaktadır.
- Faz 5 kararları K-092…K-097 ile kilitlendi; açık A kararı yoktur.


## 02.10.2026 Faz 5 başlangıcı

- Faz 5 Kasa/Banka/Çek-Senet dokümantasyonu tamamlandı.
- K-092…K-097 kilitlidir; açık A kararı yoktur.
- Veri modeli 36–38, iş kuralları 35–38 ve G-500…G-509 hazırdır.
- K-075/K-082/K-062/K-078 önceki kaynak kararları korunmuştur.
- Faz 6 kararları K-098…K-113 ile kilitlendi; Faz 6 dokümantasyonu tamamlandı.


## 02.10.2026 Faz 6 ön kararları

- Faz 6 satış + alış iadesini kapsar.
- Kaynaklı ve kaynaksız iade desteklenir.
- Satış iadesi stock in + quarantine + customer credit; alış iadesi stock out + supplier debit üretir.
- Karantina kısmi karar destekler.
- Satış iadesi maliyeti kaynaklıysa original sales out unit_cost; kaynaksızsa current moving average.
- Alış iadesi maliyet temeli kullanıcı seçimi: current moving average veya source purchase cost; kaynaksızda yalnız moving average.
- Para iadesi otomatik değildir; ayrı finans işlemidir.
- Kaynaklı iade orijinal frozen fiyat/iskonto/KDV/birim/conversion ve dövizli alışta original frozen kur kullanır.
- Önceki dönem belge mevcut açık dönemde scalar source snapshot ile iade edilebilir; eski period mutate edilmez.
- İade doğrudan yetkili kullanıcı tarafından post edilir; neden zorunludur.
- Faz 6 kararları K-098…K-113 ile kilitlendi; açık A kararı yoktur.


## 02.10.2026 Faz 6 tamamlanma

- Faz 6 İade dokümantasyonu yazıldı.
- Veri modeli 39, iş kuralları 39–41, ekranlar ve G-600…G-609 hazırdır.
- Satış iadesi stock in + quarantine + customer credit; alış iadesi stock out + supplier debit üretir.
- Cross-period iade read-only eski period + current-period frozen snapshot modelidir.
- Karantina gerçek kaynağı quarantine_entries; stock_balances.quarantine özetidir.
- Açık karantina dönem devrinde kaybolmaz, yeni döneme açık miktar/snapshot taşınır.
- Faz 7 kararları K-114…K-130 ile kilitlidir; açık A kararı yoktur.
- Faz 7 kararları K-114…K-130 ile kilitlendi; Faz 7 dokümantasyonu tamamlandı.


## 02.10.2026 Faz 7 ön kararları

- Faz 7 İthalat kararları K-114…K-129 ile kilitlendi.
- Purchase invoice ilk stok/maliyet etkisini Faz 4 kurallarıyla üretir; import finalize yalnız miktarı değiştirmeyen ek maliyet düzeltmesi yapar.
- Masraf dağıtımı: alış değeri / miktar / manuel.
- Masraf tipleri sabit temel liste + other.
- Kaynak masraf hem purchase_invoice hem manuel import expense olabilir.
- İndirilebilir ithalat KDV'si stok maliyetine girmez.
- Farklı dövizli kaynak belgeler kendi frozen kurlarıyla base currency maliyet havuzunda birleşebilir.
- Purchase invoice line import dosyasına tam satır bazında bağlanır; miktar bazlı bölünmez.
- Finalize immutable; sonradan masraf ayrı import cost adjustment'tır.
- Miktar değiştirmeyen maliyet gerçek kaynağı inventory_cost_adjustments'tır.
- product_costs.import_cost son finalized import unit cost snapshot'ıdır.
- Import file cari hareket üretmez.
- Yaşam döngüsü draft → cost_collection → finalized → adjusted.
- Bir import file birden fazla purchase invoice/supplier içerebilir.
- Dağıtım yuvarlama farkı son uygun satıra verilir.
- Ağırlık/hacim ilk sürümde yoktur.
- Açık A kararı yoktur.


## 02.10.2026 Faz 7 dokümantasyon durumu

- Veri modeli 40 yazıldı.
- İş kuralları 42–44 yazıldı.
- İthalat ekranları ve G-700…G-709 hazırdır.
- K-114…K-129 kilitlidir.
- K-130: import cost adjustment birim farkı original import base_quantity üzerinden hesaplanır; current stock quantity payda değildir ve geçmiş satış maliyetleri geriye dönük değiştirilmez.
- Açık A kararı yoktur.


## 02.10.2026 Faz 8 ön kararları

- Faz 8 basit iç üretim + fason birlikte kapsamdadır.
- K-131…K-162 kilitlidir; açık A kararı yoktur.
- Tek aktif immutable reçete revizyonu, output_quantity tabanı ve production-order snapshot vardır.
- Actual consumption kullanıcı tarafından düzeltilebilir; component-level fire manuel ve mamul maliyetine dahildir.
- Component source location satır bazında seçilir.
- Production output birden fazla target location'a bölünebilir.
- Kısmi completion ve kalan iptal vardır.
- Completion transaction'ında component out + finished product in birlikte yazılır.
- Production cost actual consumed component moving-average snapshot maliyetlerinden gelir; ilk sürümde iç üretim overhead yoktur.
- Fasoncu contact + subcontractor location modelidir; fason stok normal satışta kullanılamaz.
- Fason hizmet faturası normal purchase_invoice'dır ve mamul maliyetine dahil edilir.
- Geç gelen fason maliyeti inventory_cost_adjustments reason=subcontract_late_cost ile işlenir.
- Production-mode satış siparişi draft production order otomatik açabilir.
- Reçeteler dönem devrinde taşınır; açık production order taşınmaz.
- Production completion immutable reverse ile terslenebilir.


## 02.10.2026 Faz 8 tamamlanma

- Faz 8 Basit üretim/fason dokümantasyonu tamamlandı.
- K-131…K-162 kilitlidir; açık A kararı yoktur.
- Veri modeli 41, iş kuralları 45–47, ekranlar ve G-800…G-809 hazırdır.
- Reçete immutable Rev.N + output_quantity tabanlıdır.
- Production order reçete/component/unit/conversion snapshot taşır ve kısmi completion destekler.
- Actual consumption + component-level fire stoktan çıkar; fire maliyete dahildir.
- Component source location satır bazında, production output birden fazla target location'a bölünebilir.
- Production stock-in moving_average günceller; product_costs.production_cost son production unit cost snapshot'ıdır.
- Fasoncu contact + subcontractor location modelidir; normal satışta kullanılmaz.
- Fason hizmet purchase_invoice üzerinden production order'a bağlanır ve mamul maliyetine dahil edilir.
- Geç gelen fason hizmet inventory_cost_adjustments reason=subcontract_late_cost ile işlenir.
- Açık production order dönem devrinde taşınmaz; reçeteler ve fason location fiziksel stokları taşınır.
- Faz 9 kullanıcı onayı olmadan başlatılmaz.


## 03.10.2026 Faz 9 tamamlanma

- Faz 9 E-ticaret dokümantasyonu tamamlandı.
- K-163…K-201 kilitlidir; açık A kararı yoktur.
- Veri modeli 42, iş kuralları 48–51, ekranlar ve G-900…G-910 hazırdır.
- Kanal hesapları Master DB; listing/order/sync period DB'dedir.
- Dönemler arası external-event duplicate engeli Master registry ile korunur.
- Stock mode listing'e seçilmiş location kapsamını kullanır; production/manual miktarlar listing bazındadır.
- Imported order confirmed sales_order'dır; marketplace buyer/shipping snapshot'tır ve import tahsilat üretmez.
- Cancel mevcut remaining-cancel mantığı; return draft sales_return üretir.
- Webhook birincil, 15 dk polling yedek; 30/60/120 retry + kalıcı sync history/error vardır.
- Faz 10 kullanıcı onayı olmadan başlatılmaz.


## 03.10.2026 Faz 9 karar özeti

- Faz 9 kararları K-163…K-201 ile kilitlendi; açık A kararı yoktur.
- A-097 seçimi K-174 olarak işlendi: stock mode tüm depoları değil listing'e atanmış location kapsamını kullanır.


## 03.10.2026 Faz 10 kapsam kararı

- Kullanıcı soru sorulmadan en geniş Faz 10 sisteminin yazılmasını istedi.
- K-202…K-235 ile rapor motoru, export, çok dönem, dashboard, document/label template ve printing kapsamı kilitlendi.
- Genel muhasebe/GİB/resmi mali tablo kapsam dışı kalır.
