# 19 — Açık Kararlar

Bu dosya yalnız gerçekten açık kalan konuları tutar. `00_KARAR_KAYDI.md` içinde kilitlenmiş kararlar yeniden tartışma listesine alınmaz.

## A-01 — Negatif stok politikası
Varsayılanın engelleme mi yoksa permission + uyarı ile izin mi olacağı uygulama öncesi kesinleştirilecek. Her durumda bypass merkezi policy üzerinden olur.

## A-02 — Sevkiyat/fatura stok authority detayı
Satışta aynı fiziksel çıkışın iki kez oluşmaması temel invarianttır. Operasyon ihtiyacına göre stock OUT'un dispatch posting veya invoice posting tarafından üretilmesi seçilecek; aynı source chain iki kez üretmeyecek.

## A-03 — Dövizli virman/kur farkı
Farklı para birimli treasury hesapları arasında virman gerekiyorsa kur kaynağı, rounding ve kur farkı işlemi ayrıca kilitlenecek.

## A-04 — E-Belge sağlayıcısı
E-Fatura/e-Arşiv için kullanılacak provider ve API sözleşmesi entegrasyon aşamasında seçilecek. Domain fatura modeli provider'a bağımlı tasarlanmayacak.

## A-05 — Harici dosya storage
İlk deploy local/private storage olabilir. Offsite/S3-compatible hedef backup ve ölçek ihtiyacına göre kesinleştirilecek.

## A-06 — Lot/seri ihtiyacı
V1 core kapsam dışı. Gerçek operasyon ihtiyacı ortaya çıkarsa ayrı karar ile eklenir; şimdiden UI/table yığını kurulmaz.

## A-07 — Çoklu fiyat ihtiyacı
V1 ürün başına tek satış + tek alış fiyatı. Kanal/cari özel fiyat ihtiyacı cari iskonto ve entegrasyon kuralıyla yetmezse ayrıca tasarlanır.

## Kapanış kuralı
Bir açık karar sonuçlandığında bu dosyadan çıkarılıp `00_KARAR_KAYDI.md` içine numaralı kilitli karar olarak taşınır.
