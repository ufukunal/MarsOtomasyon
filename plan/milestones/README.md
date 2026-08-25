# MarsOtomasyon — Milestone Uygulama Takibi M1–M32

Bu klasör Master Plan V4.2'nin **uygulanabilir, işaretlenebilir ve raporlanabilir yürütme defteridir**.

## Zorunlu çalışma sırası

Her milestone/alt kırılım:

`entry gate → schema → domain action/use-case → transaction/invariant → authorization → V16.3/onaylı UI → tests → PostgreSQL CI → audit/observability → exit gate`

## İşaretleme ve raporlama kuralı

- `[x]` yalnız gerçek kod + test + acceptance/CI kanıtı bulunan işi ifade eder.
- Kod mevcut ama acceptance/CI açık ise `[ ]` kalır; alt rapor **KISMEN UYGULANDI** olarak yazılır.
- Her alt kırılımın hemen altında **Tamamlanma Raporu / İlerleme Raporu** bulunur.
- Tamamlandığında rapora en az: tarih, commit/PR, CI/test kanıtı, reconciliation sonucu ve blocker yazılır.
- Bir regression tamamlanmış işi bozarsa checkbox tekrar açılır ve rapora neden yazılır.
- Milestone tüm zorunlu kırılımlar + exit gate'leri geçince dosya sonundaki **Milestone Kapanış Raporu** doldurulur.
- M24 V1 go-live gate'idir. M25–M32 post-V1 olup M24'ü bloklamaz.

## Güncel durum

| Milestone | Başlık | Durum |
|---|---|---|
| [M1](M01.md) | Core / Company / Users / Settings / UI Shell | ✅ Tamamlandı |
| [M2](M02.md) | Cari Core | ✅ Tamamlandı |
| [M3](M03.md) | Ürün / Katalog | ✅ Tamamlandı |
| [M4](M04.md) | Stok / Depo / Cost Foundation | 🟡 Devam ediyor — M4.1 kapalı, M4.2 aktif |
| [M5](M05.md) | Teklifler / Tax Calculation Contract | ⏳ Bekliyor |
| [M6](M06.md) | Satış Siparişleri | ⏳ Bekliyor |
| [M7](M07.md) | İrsaliye / Sevkiyat | ⏳ Bekliyor |
| [M8](M08.md) | Satış Faturaları | ⏳ Bekliyor |
| [M9](M09.md) | Satınalma | ⏳ Bekliyor |
| [M10](M10.md) | Tahsilat / Ödeme / Kasa / Banka / Treasury | ⏳ Bekliyor |
| [M11](M11.md) | Çek / Senet | ⏳ Bekliyor |
| [M12](M12.md) | Return / RMA Core | ⏳ Bekliyor |
| [M13](M13.md) | Report Platform + Commercial Reports | ⏳ Bekliyor |
| [M14](M14.md) | Basit Üretim | ⏳ Bekliyor |
| [M15](M15.md) | Fason | ⏳ Bekliyor |
| [M16](M16.md) | İthalat / Konteyner | ⏳ Bekliyor |
| [M17](M17.md) | E-Ticaret Core + WooCommerce | ⏳ Bekliyor |
| [M18](M18.md) | Marketplace Adapter Pack | ⏳ Bekliyor |
| [M19](M19.md) | B2B / Bayi | ⏳ Bekliyor |
| [M20](M20.md) | Communication / Integrations / API | ⏳ Bekliyor |
| [M21](M21.md) | Product Image Operations | ⏳ Bekliyor |
| [M22](M22.md) | Product Installation PDF Builder | ⏳ Bekliyor |
| [M23](M23.md) | Security / Backup / Hardening | ⏳ Bekliyor |
| [M24](M24.md) | Migration / Go-Live | ⏳ V1 Go-Live Gate |
| [M25](M25.md) | Product Family / Variant | ⏳ Post-V1 |
| [M26](M26.md) | Barkod / Termal Etiket | ⏳ Post-V1 |
| [M27](M27.md) | Mobil Depo / Scanner | ⏳ Post-V1 |
| [M28](M28.md) | Kargo API Adapterları | ⏳ Post-V1 |
| [M29](M29.md) | OCR Fatura / Dekont | ⏳ Post-V1 |
| [M30](M30.md) | Hafif CRM | ⏳ Post-V1 |
| [M31](M31.md) | BI Export | ⏳ Post-V1 |
| [M32](M32.md) | CAD / 3D Viewer | ⏳ Post-V1 |

## Wave özeti

- **Wave A:** M1–M2 — Core + Cari ✅
- **Wave B:** M3–M9 — Ürün / Stok / Satış / Alış — M3 ✅, M4 aktif
- **Wave C:** M10–M13 — Finans / Çek-Senet / İade / Rapor
- **Wave D:** M14–M16 — Üretim / Fason / İthalat
- **Wave E:** M17–M20 — E-Ticaret / Marketplace / B2B / Communication / API
- **Wave F:** M21–M24 — Media / Hardening / Migration / Go-Live
- **Wave G:** M25–M32 — Post-V1 genişlemeler

## Her çalışma/sohbet sonunda zorunlu işlem

1. İlgili milestone dosyasını aç.
2. Gerçekten tamamlanan alt kırılımı `[x]` yap.
3. Hemen altındaki rapora **tarih + commit/PR + CI/test + yapılanlar + blocker** yaz.
4. Kısmi iş `[x]` yapılmaz; `KISMEN UYGULANDI` olarak kaydedilir.
5. Aktif alt kırılım ve sıradaki alt kırılım belirtilir.
6. Tüm exit gate'leri geçtiyse Milestone Kapanış Raporu doldurulur.
7. Bu README'deki durum tablosu milestone kapanışında güncellenir.

## Otorite belgeleri

- [`../README.md`](../README.md) — Master Plan V4.2
- [`../16_UYGULAMA_SIRASI_MILESTONE.md`](../16_UYGULAMA_SIRASI_MILESTONE.md) — high-level resmi milestone sırası
- [`../06_IS_KURALLARI_VE_INVARIANTLAR.md`](../06_IS_KURALLARI_VE_INVARIANTLAR.md) — invariantlar
- [`../14_TEST_CI_KALITE.md`](../14_TEST_CI_KALITE.md) — test/CI
- [`../18_DEFINITION_OF_DONE.md`](../18_DEFINITION_OF_DONE.md) — Definition of Done
- [`../19_ACIK_KARARLAR.md`](../19_ACIK_KARARLAR.md) — entry gate / açık kararlar
- [`../26_V16_3_TASARIM_UYUMU.md`](../26_V16_3_TASARIM_UYUMU.md) — V16.3 UI contract
- [`../27_GELECEK_GENISLEME_ALTYAPISI.md`](../27_GELECEK_GENISLEME_ALTYAPISI.md) — extension seam yöntemi
- [`../28_PLANLI_GENISLEMELER.md`](../28_PLANLI_GENISLEMELER.md) — M25–M32 post-V1 roadmap

## Durum sözlüğü

- ✅ **TAMAMLANDI:** Kod + test + kabul/CI kanıtı mevcut.
- 🟡 **KISMEN UYGULANDI / DEVAM EDİYOR:** Kodun bir kısmı mevcut, fakat zorunlu gate açık.
- ▶️ **SIRADAKİ:** Önceki milestone kapalı; entry gate açık; henüz kod commit'i başlamadı.
- ⏳ **BEKLİYOR:** Henüz başlanmadı veya entry gate açılmadı.
- ⛔ **BLOCKED:** Dış karar/altyapı/contract nedeniyle ilerlenemiyor; blocker raporda açıkça yazılır.
