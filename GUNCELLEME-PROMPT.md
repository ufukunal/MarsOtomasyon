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
- Faz 4 Alış başlatıldı; A-009…A-013 açık kararları karar günlüğünde tutulur ve kullanıcı kararı olmadan kapatılmaz.

Repo tutarlılık temizliği ve Faz 0–2 görev revizyonu tamamlandı. **Faz 3 Satış dokümantasyonu yazıldı; G-300…G-312 hazırdır. Faz 4 Alış kullanıcı onayıyla başlatıldı; G-400 kapsam belgesi oluşturuldu ve A-009…A-013 açık kararları kapatılmadan alış iş akışı varsayılmayacaktır.**
