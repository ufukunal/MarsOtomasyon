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

Repo tutarlılık temizliği ve Faz 0–2 görev revizyonu tamamlandı. **Faz 3 Satış dokümantasyonu yazıldı; G-300…G-312 hazırdır. Faz 4 Alış dokümantasyonu yazıldı; G-400…G-409 hazırdır. Faz 5 Kasa/Banka/Çek-Senet dokümantasyonu da yazıldı; K-092…K-097 kilitli, veri modeli 36–38, iş kuralları 35–38, finans ekranları ve G-500…G-509 hazırdır. Faz 6 kullanıcı onayı olmadan başlatılmaz.**


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
- Faz 6 kararları K-098…K-113 ile kilitlendi; Faz 6 dokümantasyonu başlatıldı.


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
