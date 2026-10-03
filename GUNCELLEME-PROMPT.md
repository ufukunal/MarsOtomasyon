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

Repo tutarlılık temizliği ve Faz 0–2 görev revizyonu tamamlandı. **Faz 3–10 dokümantasyonları, Faz 11b dönem devri ve Faz 11 canlı geçiş dokümantasyonu yazıldı. Açık kararlar A-125 ve A-126'dır; A-127 K-256 ile kapatıldı.**


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
- Açık kararlar A-125 ve A-126'dır; A-127 K-256 ile kapatıldı.


## 02.10.2026 Faz 7 dokümantasyon durumu

- Veri modeli 40 yazıldı.
- İş kuralları 42–44 yazıldı.
- İthalat ekranları ve G-700…G-709 hazırdır.
- K-114…K-129 kilitlidir.
- K-130: import cost adjustment birim farkı original import base_quantity üzerinden hesaplanır; current stock quantity payda değildir ve geçmiş satış maliyetleri geriye dönük değiştirilmez.
- Açık kararlar A-125 ve A-126'dır; A-127 K-256 ile kapatıldı.


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


## 03.10.2026 Faz 9 karar özeti

- Faz 9 kararları K-163…K-201 ile kilitlendi; açık A kararı yoktur.
- A-097 seçimi K-174 olarak işlendi: stock mode tüm depoları değil listing'e atanmış location kapsamını kullanır.


## 03.10.2026 Faz 10 kapsam kararı

- Kullanıcı soru sorulmadan en geniş Faz 10 sisteminin yazılmasını istedi.
- K-202…K-235 ile rapor motoru, export, çok dönem, dashboard, document/label template ve printing kapsamı kilitlendi.
- Genel muhasebe/GİB/resmi mali tablo kapsam dışı kalır.


## 03.10.2026 Faz 10 tamamlanma

- Faz 10 Raporlar/çıktılar/tasarımcı dokümantasyonu tamamlandı.
- K-202…K-235 kilitlidir; açık A kararı yoktur.
- Veri modeli 43, iş kuralları 52–56, ekranlar ve G-1000…G-1012 hazırdır.
- Geniş rapor kataloğu satıştan e-ticarete tüm operasyonel alanları kapsar.
- PDF/XLSX/CSV, queue export, dashboard, preset/kolon/drill-down ve çok dönem raporu vardır.
- Belge/etiket template'leri Master DB'de immutable Rev.N; serbest SQL/PHP/Blade yoktur.
- PrintManager tek giriş noktasıdır; ürün/koli etiketi ve print history/toplu baskı vardır.
- Genel muhasebe, resmi mali tablo ve GİB/e-belge kapsam dışıdır.


## 03.10.2026 Faz 11b ve Faz 11 tamamlanma

- Faz 11b G-1110…G-1113 tamamlandı; açık quarantine carry kuralı Faz 6 ile uyumlu hale getirildi.
- Production recipe/revision, subcontractor location stock ve channel listing/location mapping carry kapsamına işlendi.
- Faz 11 K-236…K-255 ile kilitlendi.
- Veri modeli 44, iş kuralları 58–61, sistem sağlığı/backup/deployment ekranları ve G-1101…G-1109 hazırdır.
- Production readiness: immutable deploy, Master→migrate:periods, recovery-set backup, restore prova, archive read-only, health/alert, secret/least privilege, cutover/rollback.
- Tüm planlama/dokümantasyon fazları tamamlandı; kodlama/uygulama kabul testlerine bağlıdır.


## 03.10.2026 kodlama öncesi bütünlük taraması

- K-001…K-258 eksiksiz ve tekrarsızdır.
- 156 G görev dosyasında görev kimliği çakışması yoktur.
- Teknik düzeltmeler: locations alan adı kind; subcontract late cost production_completion provenance; production_service_invoices mapping; channel_account_period_settings; print template FK migration sözleşmesi; stale faz-onay metinleri.
- A-125 K-257 ile kapandı: purchase_invoice `stock|service` satırlarını birlikte taşıyabilir; service satır stok/moving-average üretmez.
- A-126 K-258 ile kapandı: fason hizmet maliyeti completion quantity oranında deterministik allocation kayıtlarına dağıtılır.
- A-127 K-256 ile kapandı: açık sales_order/purchase_order yalnız kalan miktarlarıyla target period'da yeni confirmed snapshot'a dönüşür; sales-order aktif rezervasyonları location bazında yeniden kurulur; teklif/taslak aktarılmaz.
- Açık ürün kararı yoktur.


## 03.10.2026 K-256 dönem devri sipariş kararı

- A-127 kapandı.
- Açık sales_order ve purchase_order yeni döneme aktarılır.
- Yalnız kalan açık miktarlar target period'da yeni confirmed order snapshot'ı olur.
- Source period order immutable/read-only kalır.
- Cross-period provenance period_document_carries ile tutulur.
- Sales-order aktif rezervasyonları location bazında target'ta yeniden kurulur.
- Teklif ve taslaklar aktarılmaz.
