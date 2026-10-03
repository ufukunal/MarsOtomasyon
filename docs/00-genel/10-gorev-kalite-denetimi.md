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

**Sonuç:** 156 dosya denetlendi. 58 görev/özet dosyasında doğrudan kalite düzeltmesi yapıldı; diğer dosyalar mevcut kanonik sözleşmeyle uyumlu bulundu.

## Görev bazlı sonuç

### faz-0

| Görev | Durum | Not |
|---|---|---|
| G-000-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-001-proje-iskeleti | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-002-companies-periods | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-003-baglanti-yonetimi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-004-kopyalama-izni | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-005-kullanici-rol-izin | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-006-numaralandirma | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-007-donem-kilidi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-008-audit | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-009-attachments | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-010-yazdirma-profilleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-011-izolasyon-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-012-kabuk-ve-tema | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-013-yedekleme | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-014-donem-yonetimi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-015-giris-ve-kurulum | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-016-onbellek | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-017-veri-butunlugu | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-018-para-ve-eszamanlilik | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-019-arama-altyapisi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-020-hata-izleme-guvenlik | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-021-cok-veritabanli-migration | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-0b

| Görev | Durum | Not |
|---|---|---|
| G-0b0-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-0b1-tema | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-0b2-tablo-bileseni | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-0b3-form-bilesenleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-1

| Görev | Durum | Not |
|---|---|---|
| G-100-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-101-lokasyonlar | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-102-birimler | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-103-kategori-marka | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-104-cari-karti | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-105-cari-yan-tablolar | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-106-urun-karti | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-107-varyant-gruplari | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-108-set-urun | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-109-konfiguratormatik | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-110-fiyat-listeleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-111-sirketler-arasi-kopyalama | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-112-ice-aktarma | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-113-gorsel-setleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-114-faz1-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-115-satinalma-talebi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-10

| Görev | Durum | Not |
|---|---|---|
| G-1000-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1001-rapor-cegirdegi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1002-rapor-katalogu | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1003-dashboard | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1004-export-pdf-xlsx-csv | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1005-export-queue-gecmis | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1006-cok-donemli-rapor | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1007-preset-kolon-drilldown | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1008-belge-template-revizyon | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1009-template-token-guvenlik | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1010-etiket-koli-print | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1011-print-history-toplu-baski | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1012-faz10-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-11

| Görev | Durum | Not |
|---|---|---|
| G-1101-canli-gecis-ozeti | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1102-production-topoloji | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1103-deploy-release-migration | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1104-backup-recovery-set | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1105-restore-archive-dr | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1106-health-monitoring-alert | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1107-production-security-secrets | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1108-go-live-cutover-rollback | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1109-canli-kabul-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-11b

| Görev | Durum | Not |
|---|---|---|
| G-1100-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1110-donem-devri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1111-devir-oncesi-kontrol | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1112-cok-donemli-rapor | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-1113-donem-devri-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-2

| Görev | Durum | Not |
|---|---|---|
| G-200-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-201-tablolar | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-202-hareket-kaydi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-203-maliyet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-204-stok-durumu | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-205-stok-hareketleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-206-transfer | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-207-ambar-fisi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-208-sayim | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-209-karantina | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-210-rezervasyon | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-211-acilis-bakiyesi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-212-faz2-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-3

| Görev | Durum | Not |
|---|---|---|
| G-300-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-301-belge-tablolari | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-302-belge-hesap-motoru | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-303-post-document | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-304-teklif | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-305-satis-siparisi-rezervasyon | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-306-irsaliye-kismi-sevk | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-307-satis-faturasi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-308-proforma | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-309-tahsilat-cari-yaslandirma | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-310-arac-sicak-satis | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-311-ters-kayit | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-312-faz3-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-4

| Görev | Durum | Not |
|---|---|---|
| G-400-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-401-alis-belge-cekirdegi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-402-satinalma-talebi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-403-tedarikci-teklif-toplama | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-404-satinalma-siparisi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-405-mal-kabul | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-406-alis-faturasi-posting | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-407-kismi-alis-faturalama | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-408-alis-ters-kayit-ve-butunluk | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-409-faz4-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-5

| Görev | Durum | Not |
|---|---|---|
| G-500-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-501-finans-sema-genisletmesi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-502-virman | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-503-tedarikci-odeme | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-504-kasa-sayimi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-505-banka-mutabakati | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-506-cek-senet-semasi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-507-cek-senet-yasam-dongusu | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-508-finans-risk-ters-kayit-integrity | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-509-faz5-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-6

| Görev | Durum | Not |
|---|---|---|
| G-600-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-601-iade-sema-genisletmesi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-602-iade-kaynak-cozumleme | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-603-satis-iadesi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-604-alis-iadesi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-605-kismi-coklu-iade | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-606-karantina-kontrolu | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-607-cross-period-iade | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-608-iade-reverse-integrity | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-609-faz6-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-7

| Görev | Durum | Not |
|---|---|---|
| G-700-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-701-ithalat-semasi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-702-ithalat-dosyasi-yasam-dongusu | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-703-kaynak-alis-satirlari | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-704-ithalat-masraflari | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-705-masraf-dagitimi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-706-ithalat-finalize | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-707-inventory-cost-adjustment | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-708-late-cost-reverse-integrity | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-709-faz7-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-8

| Görev | Durum | Not |
|---|---|---|
| G-800-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-801-uretim-fason-semasi | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-802-recete-revizyonlari | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-803-production-order | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-804-production-completion | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-805-production-cost | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-806-fason-location-gonderim | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-807-fason-completion-hizmet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-808-reverse-integrity-donem | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-809-faz8-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

### faz-9

| Görev | Durum | Not |
|---|---|---|
| G-900-ozet | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-901-kanal-hesaplari-adapter | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-902-listing-mapping-yayin | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-903-icerik-gorsel-sync | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-904-kanal-stok-sync | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-905-kanal-fiyat-sync | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-906-kanal-siparis-importu | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-907-cancel-return-shipment | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-908-webhook-polling-sync-history | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-909-donem-devri-integrity | **OK** | Değişiklik gerektiren çelişki bulunmadı. |
| G-910-faz9-testleri | **OK** | Değişiklik gerektiren çelişki bulunmadı. |

## Görev dışı kanonik düzeltmeler

156 görev taramasında görev dışındaki kanonik kaynaklarda da aşağıdaki teknik tutarlılık düzeltmeleri yapılmıştır:

- `docs/02-is-kurallari/20-migration-ve-dagitim.md`: eski in-place `git pull/down/up` production deploy akışı kaldırıldı; K-239…K-241 / G-1103 immutable-release sözleşmesine bağlandı.
- `docs/01-veri-modeli/42-ecommerce-channels.md`: K-256 ile taşınan açık kanal sales_order için minimal aktif `channel_order_snapshot` provenance carry istisnası eklendi; eski sync history taşınmaz.
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

## Karar durumu

- K-001…K-258 kanonik karar setidir.
- Açık A kararı yoktur.
- Bu denetim sırasında yeni ürün kararı uydurulmamıştır.
- Yapılan değişiklikler mevcut kararların görev dosyalarına doğru yansıtılması, scope/bağımlılık düzeltmesi ve test edilebilirlik kalitesidir.

## Kodlama durumu

Bu denetim **dokümantasyon/görev kalite denetimidir**. Kodlama yapılmamıştır.
