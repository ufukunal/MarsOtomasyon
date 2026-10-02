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

Repo tutarlılık temizliği ve Faz 0–2 görev revizyonu tamamlandı. **Faz 3 Satış dokümantasyonu yazıldı; G-300…G-312 hazırdır. Faz 4 Alış dokümantasyonu da yazıldı; K-086…K-091 kilitli, veri modeli 35, iş kuralları 32–34, alış ekranları ve G-400…G-409 hazırdır. Faz 5 Kasa/Banka/Çek-Senet dokümantasyonu kullanıcı onayıyla başlatıldı; kapsam ve açık kararlar G-500 ile çıkarılacaktır.**


## 02.10.2026 Faz 4 Alış güncellemesi

- K-086: esnek alış belge zinciri.
- K-087: goods_receipt operasyonel; stok+cari+maliyet yalnız purchase_invoice posting'inde.
- K-088: kısmi teslim ve kısmi faturalama esnek.
- K-089: tedarikçi ödemesi Faz 5.
- K-090: teklif karşılaştırma belge + satır bazlı; otomatik kazanan yok.
- K-091: teklif seçiminde ayrı approval/eşik yok; izin tabanlı kullanıcı seçimi.
- Dövizli alışta belge currency/exchange_rate snapshot kalır; stok maliyeti ve cari ledger frozen kurla şirket temel para birimine çevrilir.
- Faz 4'ün tek yeni seçim tablosu `purchase_quote_selections`; ortak belge/stok/cari çekirdeği yeniden kullanılmaktadır.
- Açık A kararı yoktur.
