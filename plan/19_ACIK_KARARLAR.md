# 19 — Açık Kararlar V4 — Just-in-Time Blockers

Bu dosya backlog değildir. Yalnız ilgili milestone başlamadan gerçekten kapanması gereken kararları tutar. `00_KARAR_KAYDI.md` içinde kilitlenmiş kararlar burada tekrar tartışılmaz.

## A. KAPATILAN / LOCKED
- Search: PostgreSQL FTS + `pg_trgm`.
- Cari: bakiye bazlı `account_transactions`; OpenItem yok.
- Fiyat: tek satış + tek alış; B2B = Cari İskontosu.
- Lot/seri: V1 core dışı.
- Üretim: basit üretim; routing/work-center/OEE/ECO yok.
- QMS: Mal Kabul basit `Uygun / Kontrol Bekliyor / Uygun Değil`.
- Report Designer: generic designer yok.
- Banka: canlı open-banking yok; Excel/CSV/MT940 import + reconciliation.
- B2B cari: pre-bound; siparişte cari seçimi yok.
- Credential UI: kanal credentials Kanal Ayarları; sistem integrations Ayarlar → Entegrasyonlar; secret masked.

## B. AÇIK — GERÇEK BLOCKER

### A-01 — Negatif stok politikası
Varsayılan BLOCK mu, permission + uyarı ile controlled override mı olacağı M4 Stok başlamadan kesinleştirilecek. Her durumda merkezi policy uygulanır.

### A-02 — Sevkiyat / fatura physical stock authority
Aynı fiziksel çıkış iki kez olamaz. M7 başlamadan ana policy seçilecek:
- dispatch posting authoritative stock OUT, invoice yalnız lineage/finance
veya
- dispatch operasyon progress, invoice authoritative stock OUT.

Kısmi sevk ve fiziksel depo operasyonu dikkate alınarak tek source-effect matrix yazılacak.

### A-03 — Deployment modeli
Production rollout öncesi native/CyberPanel/Docker nihai kurulum seçilecek. Domain tasarımını değiştirmez.

### A-04 — Public ID strategy
External API/B2B milestone öncesi public ULID/UUID/public code politikası kapanacak. Internal DB PK ile kullanıcı-visible ID ayrıdır.

### A-05 — KDV dahil / hariç fiyat giriş politikası
Ürün ve satış belge implementasyonunda kullanıcı fiyat girişi davranışı netleşecek. Tax calculation snapshot aynı kalacak.

### A-06 — Belge numaralandırma serileri
Satış belgeleri başlamadan final numbering policy:
- company
- document type
- year/period
- branch gerekiyorsa
kapsamı kesinleştirilecek.

### A-07 — Dövizli virman / cross-currency payment
Gerekliyse M10 öncesi:
- kur kaynağı
- işlem tarihi
- rounding
- FX difference
- account/base currency posting
kilitlenecek. V1 same-currency önceliklidir.

### A-08 — E-Belge sağlayıcısı
E-Fatura/e-Arşiv production go-live öncesi provider seçilecek. Internal Invoice/E-Document adapter provider-neutral kalır.

### A-09 — SMS sağlayıcı sırası
Netgsm primary / İleti Merkezi fallback planı production credential/test ile doğrulanacak.

### A-10 — WhatsApp sağlayıcısı
M20 Communication başlamadan Meta Cloud API veya seçilen provider netleşecek.

### A-11 — E-Posta production sağlayıcısı
SMTP/Lark-compatible SMTP, Resend, Brevo, SendGrid, Mailgun seçeneklerinden production primary/fallback M20 öncesi kapanır.

### A-12 — Kargo provider
M7 manuel sevkiyat için blocker değildir. API kargo istenirse ilgili adapter geliştirilmeden önce provider seçilir.

### A-13 — TCKN/VKN doğrulama derinliği
Cari/form tarafında yalnız format/checksum mı, harici resmi doğrulama mı yapılacağı go-live öncesi netleşir.

### A-14 — Harici dosya storage
İlk deploy local/private storage olabilir. Offsite/S3-compatible live storage gerekip gerekmediği M23 öncesi seçilir. Backup offsite zorunluluğundan bağımsız değerlendirilir.

### A-15 — Backup RPO/RTO ve storage
M23 öncesi:
- RPO
- RTO
- retention
- offsite target
- encryption/recovery key boundary
kesinleştirilir.

### A-16 — Legacy migration depth
M24 öncesi veri seti bazında:
- tam tarihçe
- belirli dönem
- açılış bakiye/stok + read-only archive
seçimi yapılır.

### A-17 — İthalat landed-cost posting policy
M16 İthalat öncesi:
- maliyetin stock value'ya hangi anda geçtiği
- provisional vs late cost
- consumed/on-hand ayrımı
- allocation basis
kilitlenecek.

### A-18 — Costing yöntemi
Maliyet raporlaması kullanılmadan önce moving-average/FIFO/uygun basit policy ve stock count positive adjustment valuation davranışı netleştirilecek. Full manufacturing costing kurulmayacak.

## C. KAPANIŞ KURALI
Açık karar sonuçlandığında:
1. bu dosyadan çıkarılır veya `KAPALI` işaretlenir,
2. `00_KARAR_KAYDI.md` içine locked karar olarak yazılır,
3. etkilenen owner plan + invariant + test aynı commit'te güncellenir.
