# MarsProject V65 — Belge Ekranları Kalan İşlerin Kaynak Kod Kapanışı

Tarih: 15.10.2026

## Durum ayrımı

**Kaynak kod geliştirmesi** ile **çalışan sistemin doğrulanması/yayın** birbirinden ayrılır. Bu kayıt ikinci aşama başarıyla tamamlandı anlamına gelmez. Kullanıcının daha önce verdiği *test çalıştırma için ayrı onay* ve *kontrollü Fresh UAT* kuralları korunur.

## Kod ve kalite bakımından eklenenler

- Çok bantlı KDV ve belge iskontosu için gerçek `CalculateDocumentTotals` test vakası; kayıt öncesi ondalık hesap ile taslak önizleme aynı sunucu hesaplayıcısına dayanır.
- Manuel satış fiyatının istemsiz değiştirilememesi, cari değişimi, taslak kirli durumunun kayda dönüşmeden kesinleştirmeye gidememesi için birim test senaryoları.
- Çok sayfalı belge yazdırmada satır bölünmesini engelleme, tablo başlıklarını tekrarlama, yazdırma sırasında sabit satır numarasını kaldırma. Bunlar tarayıcı basım görünümüdür; e-fatura, yasal PDF ve UBL şablonu değildir.
- İzole `@invalid.test` kullanıcı ve test dönemi için salt-okunur Playwright belge testi (`tests/Browser/specs/v65-document-editor-readonly.spec.ts`). Altı boş editör için 390, 768, 1024, 1440, 1920 px; mal kabul için önceden tanımlanmış izole fixture.
- `tests/Browser/README.md` içine yeni ayrı onay kapısı belgelendi.
- Mevcut V65 ortak-shell testinin artık kullanılmayan eski `empty-state` HTML işaretlemesine ait beklentisi yeni Blade komponentine uyarlandı.
- V65 ortak-shell testindeki PHP `$this` interpolasyon hatası giderildi.
- Önceki kalite çalışma kaydında görülen `CompanySwitcher` import sırası ve izole UAT demo seeder'ının düzensiz çok satırlı dizileri düzenlendi; mevcut testlerdeki boşluk kusuru temizlendi.

## Doğrulama sınırlaması

GitHub üzerinden erişilebilen **12.10.2026** tarihli kalite iş akışı kaydında frontend build, Vite manifest ve Blade view cache başarılı, **Pint** başarısızdı (o tarihte 4 biçim hatası). Bu eski kayıt, yeni `main` commit'inin derlendiğini veya güncel Pint sonucunu ispatlamaz.

Bu sohbet kapsamındaki yeni commit'lerde **birim testleri, PHP/Blade/Vite derlemesi, Larastan, Pint, Playwright ve Fresh UAT** çalıştırılmadı. PROD sunucusu ve veritabanı, operasyonel yedekleme, dönem geçişi, ambar fişi şablonları ve GitHub runner yapılandırması değiştirilmedi.

## Kalan kabul kapıları (onay ve ortam gerektirir)

| Grup | Ön koşul | Yapılması gereken |
| --- | --- | --- |
| 1. Statik kalite | Ayrı test yürütme onayı ve erişilebilir runner | PHP lint, Blade cache, Vite build, Pint, Larastan ve tüm UI kaynak sözleşmesi testleri |
| 2. Finansal regresyon | Dedicated TEST Master/Period PostgreSQL, Valkey namespace ve onay | İskonto, KDV oranı, farklı döviz, satış risk limiti, cari bakiyesi, idempotency, onay/ters kayıt |
| 3. Belge akışları | İzole test fixture ve onay | Teklif → Sipariş → İrsaliye → Fatura; Alış Siparişi → Mal Kabul → Alış Faturası |
| 4. Görsel kabul | İzole tarayıcı + V65 onaylı referans | 390/768/1024/1440/1920 px, filtreler, hareketli alanlar, çıktı |
| 5. Yasal/kurumsal çıktı | Şirket bilgileri, resmi format gereksinimi ve tasarım onayı | Teklif, sipariş, irsaliye ve fatura belgeleri için kabul edilmiş PDF/elektronik çıktı |
| 6. Fresh UAT yayın | Ayrı dağıtım onayı ve onaylı imaj | Kontrollü deployment, sağlık kontrolü, geri dönüş senaryosu ve kullanıcı kabulü |

**Kapanış kriteri:** Bu kapıların kanıtları commit SHA / CI run / UAT ekran çıktısı / kabul kaydıyla ilişkilendirilmeden 'bitirildi' denmemelidir.
