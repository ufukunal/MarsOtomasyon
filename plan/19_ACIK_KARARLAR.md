# 19 — Açık Kararlar V4.2 — Just-in-Time Entry Gates

Bu dosya backlog değildir. Yalnız ilgili milestone başlamadan gerçekten kapanması gereken kararları tutar. `00_KARAR_KAYDI.md` içinde kilitlenmiş kararlar burada tekrar tartışılmaz.

## A. KAPATILAN / LOCKED
- Search: PostgreSQL FTS + `pg_trgm`.
- Cari: bakiye bazlı `account_transactions`; OpenItem yok.
- Cari currency: V1 tek Account book currency.
- Fiyat: tek satış + tek alış; B2B = satış fiyatı − Cari İskontosu.
- KDV fiyat girişi: net core fiyat + belge seviyesinde dahil/hariç input normalization contract.
- Lot/seri: V1 core dışı.
- Negatif stok: V1 BLOCK.
- Reservation/oversell: available physical sınırı; yetersizlikte marketplace order `Sorun/Stok Eksik`.
- Satış stok authority: sevkiyat OUT; irsaliyesiz direct invoice kendi OUT effect'ini üretir.
- Costing: moving weighted average.
- Treasury authority: `treasury_movements`.
- Transfer/fason: in-transit/subcontract custody quantity + carrying value korunur.
- Marketplace finance: legal customer snapshot + channel clearing account + payout/fee settlement ayrımı.
- B2B auth: internal User/RBAC'den ayrı external auth context.
- Üretim: basit üretim; routing/work-center/OEE/ECO yok.
- QMS: Mal Kabul basit quantity split + `Uygun / Kontrol Bekliyor / Uygun Değil`.
- Report Designer: generic designer yok.
- Banka: canlı open-banking yok; Excel/CSV/MT940 import + reconciliation.
- B2B cari: pre-bound; siparişte cari seçimi yok.
- Credential UI: kanal credentials Kanal Ayarları; sistem integrations Ayarlar → Entegrasyonlar; secret masked.
- Belge numarası baseline: company + document type + year/period; branch yalnız gerektiğinde; legal number posting/finalization'da ayrılabilir.
- Planlı M25–M31 genişleme seti: Product Family/Variant, Barkod/Termal Etiket, Mobil Depo/Scanner, Kargo API Adapterları, OCR Fatura/Dekont, Hafif CRM, BI Export.

## B. AÇIK — GERÇEK BLOCKER

### A-03 — Deployment modeli
Production rollout öncesi native/CyberPanel/Docker nihai kurulum seçilecek. Domain tasarımını değiştirmez.

### A-04 — Public ID strategy
External API/B2B milestone öncesi public ULID/UUID/public code politikası kapanacak. Internal DB PK ile kullanıcı-visible ID ayrıdır.

### A-07 — Dövizli virman / cross-currency payment
Gerekliyse M10 öncesi:
- kur kaynağı
- işlem tarihi
- rounding
- FX difference
- account/base currency posting
kilitlenecek. V1 same-currency önceliklidir.

### A-08 — E-Belge sağlayıcısı
E-Fatura/e-Arşiv production kullanımı başlamadan provider seçilecek. Internal Invoice/E-Document adapter provider-neutral kalır.

### A-09 — SMS sağlayıcı sırası
Netgsm primary / İleti Merkezi fallback planı production credential/test ile doğrulanacak.

### A-10 — WhatsApp sağlayıcısı
M20 Communication production slice başlamadan Meta Cloud API veya seçilen provider netleşecek.

### A-11 — E-Posta production sağlayıcısı
SMTP/Lark-compatible SMTP, Resend, Brevo, SendGrid, Mailgun seçeneklerinden production primary/fallback M20 production slice öncesi kapanır.

### A-12 — Kargo provider
M7 manuel sevkiyat için blocker değildir. **M28 Kargo API Adapterları** provider-specific slice başlamadan seçilen provider'ın gerçek API dokümanı/credential modeli/capability seti doğrulanır. Birden fazla kargo sağlayıcısı varsa her biri ayrı atomic adapter slice olur.

### A-13 — TCKN/VKN doğrulama derinliği
Cari/form tarafında yalnız format/checksum mı, harici resmi doğrulama mı yapılacağı go-live öncesi netleşir. Internal checksum validation bu kararı beklemez.

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
M16 İthalat **cost posting slice** başlamadan:
- landed cost'un carrying value'ya hangi anda geçtiği
- provisional vs late cost
- consumed/on-hand ayrımı
- allocation basis default'u
kilitlenecek.

## C. MILESTONE ENTRY-GATE MATRİSİ
Bir milestone başlamadan aşağıdaki açık kararlar gerçekten gerekliyse kapanmış olmalıdır. Karar gerekmiyorsa milestone scope'u o capability'yi açıkça dışarıda bırakır.

| Milestone | Entry gate |
|---|---|
| M0 | Açık business kararı yok; CI/toolchain/branch-protection skeleton zorunlu |
| M1 | Belge numbering locked baseline uygulanır; deployment seçimi gerekmez |
| M2 | A-13 yalnız external resmi doğrulama aynı milestone'a alınacaksa; aksi halde checksum-only |
| M3 | Açık blocker yok; K-043 fiyat/tax normalization uygulanır |
| M4 | Açık blocker yok; K-040/K-042/K-047/K-048 uygulanır |
| M5 | Açık blocker yok; K-043 tax/discount calculation contract zorunlu |
| M6 | Açık blocker yok; reservation/quantity cap locked |
| M7 | A-12 yalnız gerçek kargo API slice'ı varsa; stock authority K-041 locked |
| M8 | A-08 yalnız gerçek e-belge provider submit bu milestone'a alınırsa |
| M9 | Açık blocker yok; moving-average costing locked |
| M10 | A-07 yalnız cross-currency treasury uygulanacaksa |
| M11 | Açık blocker yok; endorsement/reversal contract owner planında zorunlu |
| M12 | Marketplace connector yok; yalnız Return/RMA Core foundation |
| M13 | Yalnız tamamlanmış domain raporları; future domain raporları ertelenir |
| M14 | Açık blocker yok |
| M15 | Açık blocker yok; subcontract custody locked |
| M16 | A-17 cost posting slice öncesi zorunlu |
| M17 | A-04 external/public IDs kullanılmadan önce zorunlu |
| M18 | Provider registry + gerçek API contract doğrulaması her adapter entry gate'idir |
| M19 | A-04 B2B public ID/token contract öncesi zorunlu |
| M20 | A-08/A-09/A-10/A-11 yalnız ilgili production provider slice'ı için zorunlu |
| M21 | Açık blocker yok |
| M22 | Açık blocker yok |
| M23 | A-03, A-14, A-15 production hardening öncesi zorunlu |
| M24 | A-16 zorunlu; A-13 production policy de kapanmış olmalı |
| M25 Product Family/Variant | Product/SKU identity ve marketplace mapping stabil; family additive olmalı, SKU authority değişmemeli |
| M26 Barkod/Termal Etiket | Barcode + Files/Printing stabil; gerçek printer bridge gerekiyorsa cihaz/protokol contract'ı doğrulanmalı |
| M27 Mobil Depo/Scanner | M4/M7/M9 server-side use-case'leri + API auth/idempotency stabil; offline write scope dışı veya ayrı tasarlanmış |
| M28 Kargo API Adapterları | A-12 zorunlu; shipping provider registry + gerçek contract fixture zorunlu |
| M29 OCR Fatura/Dekont | Attachment security + extraction/review seam hazır; seçilen OCR engine/provider gerçek contract'ı doğrulanmış; autonomous posting yasak |
| M30 Hafif CRM | Account ve Quote authority stabil; CRM top-level navigation değiştiriyorsa yeni UI approval gerekir |
| M31 BI Export | Report/read-model/export jobs stabil; dataset/PII allow-list belirli; operational DB write-back yok |

## D. KAPANIŞ KURALI
Açık karar sonuçlandığında:
1. bu dosyadan çıkarılır veya `KAPALI` işaretlenir,
2. `00_KARAR_KAYDI.md` içine locked karar olarak yazılır,
3. etkilenen owner plan + invariant + test aynı commit'te güncellenir,
4. ilgili milestone entry gate artık kapalı karara referans verir.
