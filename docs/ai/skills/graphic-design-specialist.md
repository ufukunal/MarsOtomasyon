# Skill: Grafik Tasarım Uzmanı

## 1. Misyon
MarsOtomasyon'un görsel dilini bilgi yoğun ERP kullanımına uygun, tutarlı, profesyonel ve işlev odaklı tutar. Amaç dekoratif görünmek değil; bilgiyi hızlı okutmak, hata riskini azaltmak ve marka bütünlüğünü korumaktır.

## 2. Tasarım sistemi sabitleri
Açık kullanıcı kararıyla değişmedikçe:
- hard-edge / square control dili
- border-radius: 0
- beyaz yüzeyler
- kontrollü mavi vurgu
- yüksek bilgi yoğunluğu
- net tablo/grid çizgileri
- düşük görsel gürültü
- tek tip ikon dili

## 3. Zorunlu kaynaklar
- mevcut Mars UI örnekleri
- design token dosyaları
- ilgili ekranın UX akışı
- kullanıcı tarafından onaylanmış görsel kararlar
- print/PDF ihtiyacı

## 4. Görsel hiyerarşi
Her ekran için sırayla belirlenir:
1. Primary task
2. Primary data
3. Secondary data
4. Status
5. Actions
6. Warnings
7. Supporting metadata

İkincil bilgi primary bilginin önüne geçemez.

## 5. Typography
Kontrol:
- font family
- size scale
- weight
- line-height
- numeric alignment
- tabular number ihtiyacı
- heading/body/label ayrımı
- uppercase kullanımının sınırlı olması

Finansal ve miktarsal tabloda sayı hizası tutarlı olmalıdır.

## 6. Spacing
Random margin/padding yasaktır.
Token bazlı spacing kullanılmalı:
- xs
- sm
- md
- lg
- xl

ERP yoğunluğu korunurken kontroller birbirine yapışmamalıdır.

## 7. Renk sistemi
Renk semantiği merkezi:
- primary
- success
- warning
- danger
- info
- neutral
- disabled

Aynı durum farklı ekranda farklı renk alamaz.

Renk tek başına anlam taşımaz; ikon/metin/status ile desteklenir.

## 8. Finansal renk kullanımı
Pozitif/negatif:
- muhasebe yönü
- kâr/zarar
- artış/azalış
aynı şey değildir.

Kırmızı/yeşil otomatik olarak debit/credit anlamında kullanılmaz.

## 9. Grid ve tablo
Kontrol:
- header hierarchy
- row height
- zebra gerekiyorsa ölçülü
- numeric alignment
- totals distinction
- selection
- hover
- active row
- error row
- status badge
- column density

## 10. İkonlar
- aynı anlam = aynı ikon
- ikon metin yerine tek başına yalnız açık anlam varsa
- dekoratif ikon minimum
- stroke/fill stili tek family
- icon size token ile

## 11. Grafikler
Chart tasarımında:
- veri ön planda
- gereksiz 3D yok
- gereksiz gridline yok
- legend sade
- color palette erişilebilir
- aynı KPI aynı renk semantiğini koruyabilir
- PDF/print görünümü test edilir

## 12. State tasarımı
Her component/ekranda gerekirse:
- default
- hover
- focus
- active
- selected
- disabled
- loading
- empty
- error
- success
tasarlanır.

## 13. Formlar
- label/input alignment
- required marker
- validation message
- help text
- section separation
- readonly vs disabled görünümü
- keyboard focus görünürlüğü

## 14. Responsive
Görsel kimlik platformlar arasında korunur ama aynı layout zorlanmaz.
Mobile:
- daha az kolon
- daha büyük touch target
- görev odaklı hierarchy

## 15. Print/PDF
- renk tüketimi
- header/footer
- page break
- table continuation
- logo
- monochrome readability
- chart readability
kontrol edilir.

## 16. Anti-patternler
- büyük radius
- pill everywhere
- gradient dekorasyonu
- aşırı shadow
- dashboard'u kart çöplüğüne çevirmek
- boş alanı "modern görünüm" diye aşırı büyütmek
- aynı ekranda 5 accent color
- ikonları farklı setlerden karıştırmak

## 17. Zorunlu çıktı
- visual hierarchy
- token kullanımı
- component state'leri
- responsive davranış
- print/PDF notu
- varsa yeni design token

## 18. Definition of Done
Görsel kararlar Mars design system ile tutarlı, bilgi yoğunluğu ve okunabilirlik dengeli, status/action anlamı net.
