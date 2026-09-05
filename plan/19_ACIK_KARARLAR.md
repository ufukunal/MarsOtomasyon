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
- M0 QA/toolchain: PHP 8.5 + Laravel 13, PostgreSQL 18, Valkey, Node 24 LTS/npm, Pint, Larastan/PHPStan level 8 no-baseline, Pest 5 + Laravel plugin + Browser/Playwright.
- M0 execution order: `29_M0_ALTYAPI_UYGULAMA_SIRASI.md` içindeki M0.0→M0.10 gate zinciri.
- İthalat landed-cost: K-052; provisional masraf carrying value üretmez, final/late cost Goods Receipt cost-adjustment authority ile on-hand/consumed ayrılır; default `line_value`.
- Public/external identity: K-053; internal bigint PK private kalır, dışarı açılan Mars resource additive immutable ULID `public_id` kullanır; provider external ID ve idempotency key ayrıdır.
- Product image operations: V1 edit akışı tahribatsızdır; crop/rotate/flip/resize orijinal private byte içeriğini değiştirmeyen metadata reçetesidir. Copy/move aynı `FileAsset` byte varlığını yeni attachment ile yeniden kullanır; quarantine `FileAsset` seviyesinde globaldir ve aktif kullanım/download akışını fail-closed kapatır.
- **A-03 Deployment modeli:** production deployment modeli Docker Compose olarak kilitlendi; ayrı `postgres`, `valkey`, `app`, `worker`, `scheduler`, `web` süreçleri kullanılır.
- **A-14 Harici dosya storage:** V1 primary application file storage local/private olarak kilitlendi; public webroot storage kullanılmaz. `FileAsset` quarantine globaldir, quarantined asset link/download akışında fail-closed kapanır. Backup offsite sınırı application primary storage kararından ayrıdır.
- **A-15 Backup/RPO/RTO/storage:** offsite backup zorunlu; dedicated `mars_backup` S3-compatible disk; RPO 24 saat, RTO 4 saat, restore-drill max age 30 gün, retention 14 daily / 8 weekly / 12 monthly; backup recovery key APP_KEY'den ayrıdır ve legacy APP_KEY decrypt production'da varsayılan kapalıdır.

## B. AÇIK — GERÇEK BLOCKER

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
M7 manuel sevkiyat için blocker değildir. M28 gerçek Kargo API Adapterları başlamadan en az ilk provider ve gerçek API/seller contract doğrulanmalıdır.

### A-13 — TCKN/VKN doğrulama derinliği
Cari/form tarafında yalnız format/checksum mı, harici resmi doğrulama mı yapılacağı go-live öncesi netleşir. Internal checksum validation bu kararı beklemez.

### A-16 — Legacy migration depth
M24 öncesi veri seti bazında:
- tam tarihçe
- belirli dönem
- açılış bakiye/stok + read-only archive
seçimi yapılır.

## C. MILESTONE ENTRY-GATE MATRİSİ
Bir milestone başlamadan aşağıdaki açık kararlar gerçekten gerekliyse kapanmış olmalıdır. Karar gerekmiyorsa milestone scope'u o capability'yi açıkça dışarıda bırakır.

| Milestone | Entry gate |
|---|---|
| M0 | `29_M0_ALTYAPI_UYGULAMA_SIRASI.md`; M0.0→M0.10 sırayla, önceki gate green olmadan sonraki başlamaz |
| M1 | Belge numbering locked baseline uygulanır; deployment seçimi gerekmez |
| M2 | A-13 yalnız external resmi doğrulama aynı milestone'a alınacaksa; aksi halde checksum-only |
| M3 | Açık blocker yok; K-043 fiyat/tax normalization uygulanır |
| M4 | Açık blocker yok; K-040/K-042/K-047/K-048 uygulanır |
| M5 | Açık blocker yok; K-043 tax/discount calculation contract zorunlu |
| M6 | Açık blocker yok; reservation/quantity cap locked |
| M7 | Manuel sevkiyat; gerçek kargo provider M28'e ertelenir; stock authority K-041 locked |
| M8 | A-08 yalnız gerçek e-belge provider submit bu milestone'a alınırsa |
| M9 | Açık blocker yok; moving-average costing locked |
| M10 | A-07 yalnız cross-currency treasury uygulanacaksa |
| M11 | Açık blocker yok; endorsement/reversal contract owner planında zorunlu |
| M12 | Marketplace connector yok; yalnız Return/RMA Core foundation |
| M13 | Yalnız tamamlanmış domain raporları; future domain raporları ertelenir |
| M14 | Açık blocker yok |
| M15 | Açık blocker yok; subcontract custody locked |
| M16 | K-052 landed-cost posting policy locked; açık blocker yok |
| M17 | K-053 public ULID strategy locked; açık blocker yok |
| M18 | Provider registry + gerçek API contract doğrulaması her adapter entry gate'idir |
| M19 | K-053 public ULID strategy locked; B2B token/auth contract ayrıca M19 scope’unda |
| M20 | A-08/A-09/A-10/A-11 yalnız ilgili production provider slice'ı için zorunlu |
| M21 | Açık blocker yok; tahribatsız edit/shared-asset/quarantine semantiği locked |
| M22 | Açık blocker yok |
| M23 | A-03, A-14, A-15 kapalı; production hardening exit testleri canonical Foundation ile doğrulanır |
| M24 | A-16 zorunlu; A-13 production policy de kapanmış olmalı |
| M25 | Product/SKU authority ve additive family migration contract hazır; ek provider kararı yok |
| M26 | Printer/output format gerçek hedefi doğrulanır; barcode authority Product/Barcode olarak kalır |
| M27 | Online-first mobile action/idempotency contract hazır; offline write scope dışı |
| M28 | **A-12 zorunlu**; ilk gerçek kargo provider registry + auth + capability + fixture doğrulanmış |
| M29 | OCR provider/engine seçimi veya local parser contract net; human-review/no-autopost policy locked |
| M30 | CRM Account ownership + duplicate-conversion policy `28`deki contract'a göre uygulanır |
| M31 | BI target/dataset format gerçek consumer ile doğrulanır; read-only/no-writeback contract locked |

## D. KAPANIŞ KURALI
Açık karar sonuçlandığında:
1. bu dosyadan çıkarılır veya `KAPALI` işaretlenir,
2. `00_KARAR_KAYDI.md` içine locked karar olarak yazılır,
3. etkilenen owner plan + invariant + test aynı commit'te güncellenir,
4. ilgili milestone entry gate artık kapalı karara referans verir.
