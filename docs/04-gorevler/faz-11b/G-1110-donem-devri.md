# G-1110 — Dönem devri

## Amaç
Kaynak şirket/yıl period DB'sini bir sonraki yıla kontrollü biçimde taşımak; kart kimliklerini, stok/cari/maliyet sürekliliğini ve period erişim devri adımını korumak.

## Önkoşul
Faz 0–2 altyapısı; cari/kasa/banka/çek-senet fazları canlıya geçmeden önce tamamlanmış olmalı. G-1110 carry çekirdeği minimum preflight çağrısını içerir; G-1111 aynı `PreviewPeriodCarry` sözleşmesini ayrıntılı kontrol listesi/UI olarak genişletir.

## Dokunulacak dosyalar
- `app/Actions/Periods/CarryPeriod.php`
- `app/Actions/Periods/PreviewPeriodCarry.php`
- `app/Actions/Periods/CopyPeriodCards.php`
- `app/Actions/Periods/CopyPeriodOpenings.php`
- `app/Actions/Periods/CopyPeriodAccess.php`
- `app/Actions/Imports/CarryImportFileFromPeriod.php`
- `app/Support/Period/PeriodContext.php`
- `app/Livewire/Pages/Settings/PeriodCarry.php`
- `resources/views/livewire/pages/settings/period-carry.blade.php`
- `app/Console/Commands/IntegrityCarry.php`
- `tests/Feature/Period/CarryPeriodTest.php`

## Şema / Kod
### Action sınırı
```php
CarryPeriod::handle(Period $source, int $targetYear, string $idempotencyKey): CarryResult
```

### Zorunlu işlem sırası
1. Master erişim/yetki doğrula.
2. Preview/control checklist çalıştır.
3. Target period Master kaydını ve DB adını belirle.
4. Hedef DB oluştur.
5. migrate:periods çalıştır.
6. Kartları FK dependency sırasıyla aynı ID/kodla kopyala; `cash_accounts` ve `bank_accounts` dahil Faz 3'te eklenen kartlar da bu kapsamdadır.
7. Gerekli pasif kartları ilişki analiziyle dahil et.
8. StockBalance kimliklerini ve açılış miktarlarını hazırla.
9. **Explicit ID ile kopyalanan tabloların sequence'lerini**, aynı tabloya herhangi bir normal/auto-ID insert yapılmadan önce `MAX(id)+1` seviyesine getir. Bu kural yalnız carry sonunda yapılan toplu bir düzeltmeye bırakılamaz.
10. Opening stock movements'i closing moving average maliyetiyle yaz.
11. product_costs kopyala.
12. cari açılışlarını ve kasa/banka kapanış bakiyelerini hedefte opening movement olarak yaz; geçmiş `cash_movements` / `bank_movements` satırlarını kopyalama.
13. vadesi gelmemiş çek/senetleri taşı.
14. Açık quarantine kayıtlarını source miktar/snapshot ile taşı.
15. Aktif production recipe/revision kayıtlarını ve channel account period settings + channel listing/location mapping'lerini taşı; açık production/subcontract order taşıma. Geçmiş channel order/sync history taşıma. Bu aşamada explicit ID ile kopyalanan başka bir tablo varsa aynı sequence kuralını o tablo için de auto-ID insert'ten önce uygula.
16. K-256: source sales_order/purchase_order kalanlarını hesapla; kalan > 0 olanları target period'da yeni confirmed order snapshot'ı olarak oluştur. **Target sipariş target yılın kendi `number_series` serisinden yeni numara alır; source numara `period_document_carries.source_document_number` provenance alanında korunur.** Provenance yaz ve sales-order aktif rezervasyonlarını location bazında yeniden kur. Kaynak sales_order kanal siparişiyse gerekli `channel_order_snapshot` target order'a aktif provenance olarak yeniden bağla; sync event/error geçmişini taşıma.
17. K-259: source period'daki `draft|in_transit|customs` import dosyalarını target'ta yeni `import_file` numarasıyla snapshot olarak oluştur; source period/file/number provenance yaz. Container/package/cost item operational alanlarını kopyala, ancak kur kilidi ve hesaplanmış TRY/allocation/landed-cost snapshotlarını sıfırla; source kaydı mutate etme.
18. Carry boyunca explicit ID yazılmış tüm tabloların sequence'lerinin `MAX(id)+1` olduğunu final olarak doğrula; ardından integrity:carry çalıştır, farkta exception.
19. Kaynak period'u closed yap ve carry metadata yaz.
20. Transaction/business aşaması tamamlanınca kullanıcıya yetki devri ekranını aç.

### Actor
Period hareketlerindeki actor alanı Master user scalar id + user_name snapshot; cross-DB FK yok.

## Kurallar
- Aynı şirket dönem devrinde kartların ID ve kodları korunur.
- Taşınan stock_balance kayıtlarının ID'si korunur; stock_movements geçmişi taşınmaz.
- Explicit ID ile kopyalanan her tablonun sequence'i, aynı tabloya ilk auto-ID insert yapılmadan önce `MAX(id)+1` seviyesine alınır; carry sonunda da final olarak doğrulanır.
- Aktif kartlar ile bakiye/hareket ilişkili gerekli pasif kartlar taşınır.
- Geçmiş belgeler, açık teklif ve taslak taşınmaz. Açık sales_order/purchase_order yalnız K-256 kalan-miktar snapshot akışıyla target'ta **yeni belge** olarak oluşur. K-259 kapsamındaki açık ithalat dosyası target'ta yeni import numarası ve source provenance ile operational snapshot olarak oluşur; source dosya mutate edilmez. Target sales/purchase belge target yılın kendi numara serisinden yeni numara alır; source sales/purchase belge numarası `period_document_carries.source_document_number` ile provenance olarak korunur. Yoldaki transfer taşınmaz. **Açık karantina kayıtları miktar/snapshot ile taşınır.**
- Açılış maliyeti kaynak period kapanış moving average değeridir.
- product_costs sürekliliği korunur.
- Cari bakiye contact_transactions toplamından açılış hareketine dönüştürülür.
- Kasa/banka açılışları ve vadesi gelmemiş çek/senet taşınır.
- Master user'a period FK kurulmaz.
- Devir sonrası kullanıcı period erişim/yetki override kopyalama sorusu gösterilir.
- integrity:carry hedef açılış ↔ kaynak kapanış eşleşmesini doğrular.
- Devir state-changing ve idempotent olmalıdır.
- Devir kritik aşamaları audit/correlation id taşır.
- Target DB migrate:periods tamamlanmadan veri kopyalanmaz.
- Period tablolarında company_id yoktur.
- Money/BCMath kullanılır; float yok.
- Testler gerçek PostgreSQL'de çalışır.
- Yeni iş kararı uydurulmaz.


### Uygulama ayrıntıları
- Hedef period DB oluşturulup `migrate:periods` tamamlanmadan hiçbir kart/açılış kopyalanmaz.
- Aynı şirket devrinde taşınan bütün kart ID+kodları ve taşınan stock_balance ID'leri korunur; explicit-ID kopyalanan tabloların sequence'leri o tabloya auto-ID insert yapılmadan önce `MAX(id)+1` yapılır ve carry sonunda yeniden doğrulanır.
- Geçmiş belge/hareket, açık teklif, taslak ve yoldaki transfer taşınmaz; açık sales_order/purchase_order K-256 gereği kalan miktarla target snapshot'a dönüşür; **açık karantina kayıtları yeni period'a taşınır.**
- `integrity:carry` kaynak kapanış ile hedef açılışı doğrulamadan kaynak period closed yapılmaz.
- Devir başarıyla bittikten sonra kullanıcıya önceki dönem period erişim/permission override kayıtlarını seçerek kopyalama sorulur.

## Kabul ölçütü
- [ ] Target DB yoksa oluşturulur; varsa ikinci devir otomatik başlamaz.
- [ ] Target migration tam uygulanmadan copy başlamaz.
- [ ] Contact ID+code kaynakla aynı.
- [ ] Product ID+code kaynakla aynı.
- [ ] Location ID+code kaynakla aynı.
- [ ] Unit/category/brand/variant/set/config/price-list ID zinciri korunur.
- [ ] Foreign key dependency sırası kopyada korunur.
- [ ] Pasif ama açık bakiye ilişkili cari taşınır.
- [ ] Pasif ama stok ilişkili ürün taşınır.
- [ ] Tamamen kullanılmayan pasif kart taşınmaz.
- [ ] StockBalance ID kaynakla aynı.
- [ ] Opening quantity kaynak kapanış quantity ile aynı.
- [ ] Opening unit cost kaynak moving average ile aynı.
- [ ] Geçmiş stock_movements hedefte yok.
- [ ] Geçmiş documents hedefte yok; yalnız K-256 carry order snapshot'ları var.
- [ ] Open quote hedefte yok.
- [ ] Open sales_order kalan miktarı target confirmed order'a taşınmış; target yılın `sales_order` numara serisinden yeni numara almış ve source numarası carry provenance'ta korunmuş.
- [ ] Open purchase_order kalan miktarı target confirmed order'a taşınmış; target yılın `purchase_order` numara serisinden yeni numara almış ve source numarası carry provenance'ta korunmuş.
- [ ] Sales-order aktif reservation location dağılımı target'ta yeniden kurulmuş.
- [ ] Carried açık kanal sales_order için external order snapshot/provenance target'ta korunmuş; eski sync history yok.
- [ ] Açık satış/satınalma teklif ve draft belgeleri hedefte yok; K-259 açık import file draft snapshot istisnası uygulanmış.
- [ ] In-transit transfer hedefte yok.
- [ ] Açık quarantine kayıtları source open quantity/snapshot ile hedefte var.
- [ ] Açık `draft|in_transit|customs` import file target'ta yeni import numarasıyla taşınmış; source period/file/number provenance korunmuş.
- [ ] Carried import file üzerinde source kur kilidi, amount_try/allocation ve landed-cost snapshotları yok; target period receiving date için yeniden üretilecek durumda.
- [ ] Contact opening toplamı kaynak bakiye toplamıyla aynı.
- [ ] Cash/bank opening toplamları aynı.
- [ ] Unmatured security taşınır.
- [ ] Matured/closed security taşınmaz.
- [ ] Explicit-ID kopyalanan tabloda ilk auto-ID insert'ten önce ilgili sequence `MAX(id)+1` değerine alınır; carry sonunda tüm ilgili sequence'ler tekrar doğrulanır.
- [ ] integrity:carry farkta source period'u kapatmaz.
- [ ] Başarılı carry source period'u closed yapar.
- [ ] carried_at metadata yazılır.
- [ ] İkinci aynı idempotency key çift carry üretmez.
- [ ] Unexpected failure target'ı yarım aktif bırakmaz.
- [ ] Yetki kopyalama carry bittikten sonra sorulur.
- [ ] Seçilmeyen kullanıcı yeni period erişimi almaz.
- [ ] Seçilen kullanıcı period_user_access kopyası alır.
- [ ] permission_overrides varsa kopyalanır.
- [ ] Role definitions Master'da tekrar yaratılmaz.
- [ ] Rollback yalnız target'ta iş kaydı yoksa destructive olabilir.
- [ ] Target'a iş kaydı girdiyse otomatik drop yapılmaz.
- [ ] Activity log actor id/name snapshot taşır.
- [ ] Cross-DB user FK yoktur.
- [ ] Gerçek PostgreSQL restore/migrate testi geçer.
- [ ] Pint/Larastan/Pest geçer.

## İstem
> CarryPeriod ve önizleme/erişim kopyalama akışını bu dosyadaki sıraya göre uygula. Kart/stock_balance ID sürekliliğini bozma; geçmiş hareket/belge taşıma; K-256 açık sipariş carry snapshot'larını istisna olarak uygula; integrity:carry geçmeden source period'u kapatma.
