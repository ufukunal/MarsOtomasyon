# 156 görev kalite denetimi — 03.10.2026

## Kapsam

Bu denetimde `docs/04-gorevler/` altındaki **156 G dosyasının tamamı tek tek okunmuştur**.

Her dosyada şu rubric uygulanmıştır:

1. Amaç ve faz sınırı,
2. Önkoşul / bağımlılık yönü,
3. Dokunulacak dosyaların standalone uygulanabilirliği,
4. Şema / Kod sözleşmesinin güncel kanonik modelle uyumu,
5. Karar günlüğü K kararlarıyla uyum,
6. Başka fazın sorumluluğunu yanlışlıkla üstlenme / duplicate altyapı,
7. Kuralların birbirleriyle ve dönem devriyle uyumu,
8. Kabul ölçütlerinin deterministik ve test edilebilir olması,
9. PostgreSQL / Money-BCMath / permission / cross-DB sınırları,
10. Stale isim, alan, karar veya faz-onay metni.

**Sonuç:** 156 dosya denetlendi. **68 görev/özet dosyasında doğrudan kalite düzeltmesi yapıldı**; diğer dosyalar mevcut kanonik sözleşmeyle uyumlu bulundu.

## Görev bazlı sonuç

### faz-0

| Görev | Durum | Not |
|---|---|---|
| G-000 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-001 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-002 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-003 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-004 | **DÜZELTİLDİ** | generic dosya kapsamı somutlaştırıldı |
| G-005 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-006 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-007 | **DÜZELTİLDİ** | rol sabiti yerine permission modeli |
| G-008 | **DÜZELTİLDİ** | audit dosya kapsamı somutlaştırıldı |
| G-009 | **DÜZELTİLDİ** | generic dosya kapsamı somutlaştırıldı + integrity:files acceptance |
| G-010 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-011 | **DÜZELTİLDİ** | izolasyon test dosyaları somutlaştırıldı + integrity:files failure testi |
| G-012 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-013 | **DÜZELTİLDİ** | Faz 11 backup genişletmesiyle sahiplik sınırı |
| G-014 | **DÜZELTİLDİ** | period permission + dosya kapsamı |
| G-015 | **DÜZELTİLDİ** | auth/setup dosya kapsamı |
| G-016 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-017 | **DÜZELTİLDİ** | integrity:numbers altyapı acceptance + Faz 3 sahiplik sınırı |
| G-018 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-019 | **DÜZELTİLDİ** | pg_trgm period DB migration'a taşındı |
| G-020 | **DÜZELTİLDİ** | Faz 11 health/security sahiplik sınırı |
| G-021 | **DÜZELTİLDİ** | stale in-place deploy kaldırıldı; yalnız migrate:periods |

### faz-0b

| Görev | Durum | Not |
|---|---|---|
| G-0b0 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-0b1 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-0b2 | **DÜZELTİLDİ** | DataTable dosya kapsamı somutlaştırıldı |
| G-0b3 | **DÜZELTİLDİ** | form/lookup dosya kapsamı somutlaştırıldı |

### faz-1

| Görev | Durum | Not |
|---|---|---|
| G-100 | **DÜZELTİLDİ** | G-115 kapsam sahipliği güncellendi |
| G-101 | **DÜZELTİLDİ** | location dosya kapsamı + Faz 8 subcontractor genişletme sınırı |
| G-102 | **DÜZELTİLDİ** | unit dosya kapsamı |
| G-103 | **DÜZELTİLDİ** | category/brand dosya kapsamı |
| G-104 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-105 | **DÜZELTİLDİ** | contact yan tablo dosya kapsamı |
| G-106 | **DÜZELTİLDİ** | product dosya kapsamı |
| G-107 | **DÜZELTİLDİ** | variant dosya kapsamı |
| G-108 | **DÜZELTİLDİ** | set dosya kapsamı |
| G-109 | **DÜZELTİLDİ** | configurator dosya kapsamı |
| G-110 | **DÜZELTİLDİ** | price list dosya kapsamı |
| G-111 | **DÜZELTİLDİ** | cross-company copy dosya kapsamı |
| G-112 | **DÜZELTİLDİ** | import dosya kapsamı |
| G-113 | **DÜZELTİLDİ** | attachment-based görsel set dosya kapsamı |
| G-114 | **DÜZELTİLDİ** | Faz 1 test dosya kapsamı |
| G-115 | **DÜZELTİLDİ** | Faz 1 scope taşması kaldırıldı; implementation Faz 4'e bırakıldı |

### faz-10

| Görev | Durum | Not |
|---|---|---|
| G-1000 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1001 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1002 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1003 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1004 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1005 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1006 | **DÜZELTİLDİ** | G-1112 dairesel bağımlılığı kaldırıldı; kanonik implementation sahibi |
| G-1007 | **DÜZELTİLDİ** | integrity:report-presets görev sahipliği ve acceptance |
| G-1008 | **DÜZELTİLDİ** | integrity:templates görev sahipliği ve acceptance |
| G-1009 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1010 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1011 | **DÜZELTİLDİ** | integrity:print-provenance görev sahipliği ve acceptance |
| G-1012 | **DÜZELTİLDİ** | Faz 10 üç integrity kontrolü + integrity:all acceptance |

### faz-11

| Görev | Durum | Not |
|---|---|---|
| G-1101 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1102 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1103 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1104 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1105 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1106 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1107 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1108 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1109 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-11b

| Görev | Durum | Not |
|---|---|---|
| G-1100 | **DÜZELTİLDİ** | K-256 açık sipariş carry özeti düzeltildi |
| G-1110 | **DÜZELTİLDİ** | K-256 channel snapshot + G-1111 sahiplik sınırı + carry numaralandırma/sequence sırası netleştirildi |
| G-1111 | **DÜZELTİLDİ** | duplicate carry motoru riski kaldırıldı; preview genişletmesi |
| G-1112 | **DÜZELTİLDİ** | copy-paste carry kuralları kaldırıldı; G-1006 reuse entegrasyonu |
| G-1113 | **DÜZELTİLDİ** | K-256 channel snapshot carry testleri |

### faz-2

| Görev | Durum | Not |
|---|---|---|
| G-200 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-201 | **DÜZELTİLDİ** | stok şema/model/test dosya kapsamı |
| G-202 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-203 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-204 | **DÜZELTİLDİ** | stok durumu ekran dosya kapsamı |
| G-205 | **DÜZELTİLDİ** | stok hareket ekran dosya kapsamı |
| G-206 | **DÜZELTİLDİ** | transfer dosya kapsamı |
| G-207 | **DÜZELTİLDİ** | koli etiketi scope Faz 10'a devredildi; carton tablo uydurma kaldırıldı |
| G-208 | **DÜZELTİLDİ** | sayım frozen-snapshot fark matematiği düzeltildi |
| G-209 | **DÜZELTİLDİ** | quarantine_entries gerçek kaynak / summary ayrımı düzeltildi |
| G-210 | **DÜZELTİLDİ** | rezervasyon dosya kapsamı |
| G-211 | **DÜZELTİLDİ** | açılış stok dosya kapsamı |
| G-212 | **DÜZELTİLDİ** | float test literal kaldırıldı; sayım acceptance düzeltildi |

### faz-3

| Görev | Durum | Not |
|---|---|---|
| G-300 | **DÜZELTİLDİ** | stale sonraki-faz onay cümlesi kaldırıldı |
| G-301 | **DÜZELTİLDİ** | K-257 service line nullable şeması işlendi |
| G-302 | **DÜZELTİLDİ** | stale fiyat referansı düzeltildi |
| G-303 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-304 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-305 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-306 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-307 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-308 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-309 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-310 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-311 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-312 | **DÜZELTİLDİ** | integrity:numbers gerçek belge/series mismatch acceptance |

### faz-4

| Görev | Durum | Not |
|---|---|---|
| G-400 | **DÜZELTİLDİ** | K-257 stock/service effect matrix + stale faz onayı |
| G-401 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-402 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-403 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-404 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-405 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-406 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-407 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-408 | **DÜZELTİLDİ** | mixed/service invoice reverse semantiği |
| G-409 | **DÜZELTİLDİ** | service-only/mixed purchase invoice testleri |

### faz-5

| Görev | Durum | Not |
|---|---|---|
| G-500 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-501 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-502 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-503 | **DÜZELTİLDİ** | supplier payment currency compatibility açıklaştırıldı |
| G-504 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-505 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-506 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-507 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-508 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-509 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-6

| Görev | Durum | Not |
|---|---|---|
| G-600 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-601 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-602 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-603 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-604 | **DÜZELTİLDİ** | alış iadesi posting idempotency sözleşmesi ve acceptance |
| G-605 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-606 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-607 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-608 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-609 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-7

| Görev | Durum | Not |
|---|---|---|
| G-700 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-701 | **DÜZELTİLDİ** | K-130/K-257 kapsamı + stock source şartı |
| G-702 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-703 | **DÜZELTİLDİ** | ithal ürün kaynağı yalnız line_kind=stock |
| G-704 | **DÜZELTİLDİ** | manual import expense state-changing idempotency |
| G-705 | **DÜZELTİLDİ** | allocation persistence state-changing idempotency |
| G-706 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-707 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-708 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-709 | **DÜZELTİLDİ** | K-257 service expense/mixed invoice + expense/allocation idempotency testleri |

### faz-8

| Görev | Durum | Not |
|---|---|---|
| G-800 | **DÜZELTİLDİ** | K-257/K-258 çapraz karar referansı |
| G-801 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-802 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-803 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-804 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-805 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-806 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-807 | **DÜZELTİLDİ** | K-257 service source + K-258 allocation istemi |
| G-808 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-809 | **DÜZELTİLDİ** | K-257/K-258 test kapsamı |

### faz-9

| Görev | Durum | Not |
|---|---|---|
| G-900 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-901 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-902 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-903 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-904 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-905 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-906 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-907 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-908 | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-909 | **DÜZELTİLDİ** | K-256 carried open channel-order snapshot provenance |
| G-910 | **DÜZELTİLDİ** | K-256 kanal order carry test kapsamı |

## Görev dışı kanonik düzeltmeler

- `docs/02-is-kurallari/20-migration-ve-dagitim.md`: eski in-place production deploy akışı kaldırıldı; K-239…K-241 / G-1103 immutable-release sözleşmesine bağlandı.
- `docs/01-veri-modeli/42-ecommerce-channels.md`: K-256 ile taşınan açık kanal sales_order için minimal aktif `channel_order_snapshot` provenance carry istisnası eklendi.
- `docs/02-is-kurallari/51-kanal-webhook-sync-integrity.md`: aynı K-256 korelasyon kuralı işlendi.
- `docs/02-is-kurallari/57-donem-devri-kontrol-ve-butunluk.md`: carried open channel order provenance carry kapsamına eklendi.

## Ana bulguların kapanışı

- Faz 0 deployment/health/backup ile Faz 11 arasındaki duplicate sahiplik sınırları netleştirildi.
- Faz 1 G-115'in Faz 4 satınalma belge çekirdeğini erken/parallel kurması engellendi.
- Faz 2 sayım snapshot matematiği ve karantina gerçek-kaynak çelişkisi düzeltildi.
- Faz 3/Faz 4 K-257 service-line etkileri schema/reverse/test zincirine işlendi.
- Faz 7 import product source ile service expense source ayrıştırıldı.
- Faz 8 K-257/K-258 service-cost zinciri görev/testlerde açıklaştırıldı.
- Faz 9 ↔ K-256 dönem devri external-order korelasyonu korundu.
- Faz 10 G-1006 ↔ Faz 11b G-1112 dairesel implementation sahipliği kaldırıldı.
- Faz 11b görevlerinde K-256 açık sipariş carry ve kanal provenance semantiği tutarlı hale getirildi.
- Faz 10 `integrity:report-presets`, `integrity:templates`, `integrity:print-provenance` kontrolleri görev sahipliği ve Faz 10 test zincirine yayıldı.
- `integrity:files` Faz 0 failure acceptance'ına, `integrity:numbers` ise Faz 0 altyapı + Faz 3 gerçek document/series acceptance zincirine bağlandı.
- K-038 state-changing idempotency kuralı alış iadesi posting, manual import expense ve import allocation persistence görevlerine yayıldı; read-only/resolver görevlerine gereksiz duplicate idempotency sözleşmesi eklenmedi.

## Karar durumu

- K-001…K-258 kanonik karar setidir.
- Açık A kararı yoktur.
- Bu denetim sırasında yeni ürün kararı uydurulmamıştır.
- Yapılan değişiklikler mevcut kararların görev dosyalarına doğru yansıtılması, scope/bağımlılık düzeltmesi ve test edilebilirlik kalitesidir.

## Kodlama durumu

Bu denetim **dokümantasyon/görev kalite denetimidir**. Kodlama yapılmamıştır.
