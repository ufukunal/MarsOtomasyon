# MarsProject Demo Veri Seti — UAT FRESH

Bu klasör **2026.10.13.1** sürümündeki sentetik demo verilerinin tam arşivini, CSV/JSON kaynaklarını ve UAT Laravel içe aktarma kodunu içerir.

## Tam paketi geri oluşturma

GitHub yükleme limiti nedeniyle toplam **16.004.797 bayt** ZIP dosyası `archive/part-001.bin`–`part-009.bin` olarak saklanır. Dosyaları sırasıyla birleştirip SHA-256 hash'i doğrulamak için:

```bash
bash demo/reconstruct.sh
```

**ZIP SHA-256:** `9e3b63ccf9346e96cb21cc3a1438330684d514939c78adf8bf830029efc1be8a`

Ürün görsellerini (500 PNG) ve teknik föyleri (250 PDF) klasörlere açmak için:

```bash
bash demo/reconstruct.sh --extract
```

ZIP olmadan CSV, JSON, açıklamalar, Laravel seeder ve kontrol betikleri doğrudan bu klasörde okunabilir; **medya dosyaları yalnız ZIP parçalarının içinde bulunur**.

## Güvenli yükleme

1. Önce /kurulum sihirbazını bitirip **mars_uat_demo_2026** dönemini oluştur.
2. Fresh servisleri `healthy` iken `bash demo/deploy/uat-fresh-import.sh --check` ile salt-okunur preflight yap.
3. Yalnız doğru UAT DB doğrulanır ve kullanıcı onayı varsa `--apply` çalıştır.

Bu import yalnız fresh izole veritabanını hedefler. PROD ve eski UAT ortamında **çalıştırma**. Uygulama üzerinden kesinleştirilmemiş belgeler gerçek stok/muhasebe hareketi sayılmaz.

---
# MarsProject / 2026 UAT Kapsamlı Demo Veri Seti

**UYARI:** Bu set tamamen sentetiktir. Fotoğraf değildir; ürün görselleri oluşturulmuş temsili çizimlerdir. Teknik föyler ölçüm/sertifikasyon değildir.

**HEDEF:** Yalnızca `mars_uat_master` ve `mars_uat_demo_2026` gibi izole demo veritabanları. PROD üzerinde doğrudan SQL veya `db:seed` komutu çalıştırılmamalıdır.

## Paket
- `csv/*.csv`: uygulama şemasına göre ilişkilendirilmiş demonstrasyon tabloları ve işlem senaryoları
- `images/products/`: her ürüne özel kapak ve detay görseli (500 PNG)
- `technical_sheets/`: her ürün için teknik föy (250 PDF)
- `json/`: ürün teknik detayları ve belge iş akışları
- `manifest.json`: kayıt sayıları ve referans doğrulama bilgileri

## Belge statüleri
`scenario_status` alanı UAT'nin sonunda test edilmesi hedeflenen açık/kısmi/tamamlanan örnek senaryoyu gösterir. `database_seed_status` = `draft`, çünkü gerçek posted belge, stok hareketi, rezervasyon, cari finans kaydı ve maliyet hesaplaması **yalnız uygulamanın Domain Action katmanı üzerinden** yapılmalıdır. Bunları direkt SQL ile taklit etmek stok ve finans bütünlüğünü bozar.

## Dikkat
Bu dosyalar veri hazırlığıdır; sunucuya uygulanmış, migration çalıştırılmış veya çalışan Laravel oturumunda doğrulanmış sayılmaz. İçe aktarım için kayıt karşılıklarının Action ve FK düzeyinde eşleştirilmesi, geçerli demo şirketi/period kontrolü, idempotency ve transaction gerekir.

## Belgeler zinciri
Teklif → Satış Siparişi → İrsaliye → Satış Faturası → Tahsilat / İade; Satın Alma Siparişi → Mal Kabul → Tedarikçi Faturası → Ödeme; İthalat Dosyası → Konteyner → Koli → Ürün; Reçete → Üretim/Fason Emri. `document_links.csv` senaryo bağlantılarını taşır; bu bağlantılar ancak doğrulanmış domain işlemlerinden sonra canlı DB'ye yazılmalıdır.

## Teknik veri
Tüm ad/telefon/e-posta/adresler demo olarak etiketlidir. `example.invalid` alan adı kasıtlıdır. Banka alanları gerçek ödeme hesabı içermemektedir. Resim ve teknik çizimler temsili olup teknik iddia değildir.
