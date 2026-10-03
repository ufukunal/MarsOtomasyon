# G-1110 — Dönem devri

## Amaç
Kaynak şirket/yıl period DB'sini bir sonraki yıla kontrollü biçimde taşımak; kart kimliklerini, stok/cari/maliyet sürekliliğini ve period erişim devri adımını korumak.

## Önkoşul
Faz 0–2 altyapısı; cari/kasa/banka/çek-senet fazları canlıya geçmeden önce tamamlanmış olmalı. G-1111 kontrol listesi planlıdır; bu görev kendi minimum kontrollerini içerir.

## Dokunulacak dosyalar
- `app/Actions/Periods/CarryPeriod.php`
- `app/Actions/Periods/PreviewPeriodCarry.php`
- `app/Actions/Periods/CopyPeriodCards.php`
- `app/Actions/Periods/CopyPeriodOpenings.php`
- `app/Actions/Periods/CopyPeriodAccess.php`
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
9. Opening stock movements'i closing moving average maliyetiyle yaz.
10. product_costs kopyala.
11. cari açılışlarını ve kasa/banka kapanış bakiyelerini hedefte opening movement olarak yaz; geçmiş `cash_movements` / `bank_movements` satırlarını kopyalama.
12. vadesi gelmemiş çek/senetleri taşı.
13. Açık quarantine kayıtlarını source miktar/snapshot ile taşı.
14. Aktif production recipe/revision kayıtlarını ve channel listing/location mapping'lerini taşı; açık production/subcontract order ve channel order/sync history taşıma.
15. Sequence'leri MAX(id)+1 ayarla.
16. integrity:carry çalıştır; farkta exception.
17. Kaynak period'u closed yap ve carry metadata yaz.
18. Transaction/business aşaması tamamlanınca kullanıcıya yetki devri ekranını aç.

### Actor
Period hareketlerindeki actor alanı Master user scalar id + user_name snapshot; cross-DB FK yok.

## Kurallar
- Aynı şirket dönem devrinde kartların ID ve kodları korunur.
- Taşınan stock_balance kayıtlarının ID'si korunur; stock_movements geçmişi taşınmaz.
- Sequence'ler kopya sonrası MAX(id)+1 seviyesine alınır.
- Aktif kartlar ile bakiye/hareket ilişkili gerekli pasif kartlar taşınır.
- Belgeler, açık teklif/sipariş, taslak ve yoldaki transfer taşınmaz. **Açık karantina kayıtları miktar/snapshot ile taşınır.**
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
- Aynı şirket devrinde taşınan bütün kart ID+kodları ve taşınan stock_balance ID'leri korunur; sequence'ler `MAX(id)+1` yapılır.
- Geçmiş belge/hareket, açık teklif-sipariş, taslak ve yoldaki transfer taşınmaz; **açık karantina kayıtları yeni period'a taşınır.**
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
- [ ] Documents hedefte yok.
- [ ] Open sales order/quote hedefte yok.
- [ ] Draft hedefte yok.
- [ ] In-transit transfer hedefte yok.
- [ ] Açık quarantine kayıtları source open quantity/snapshot ile hedefte var.
- [ ] Contact opening toplamı kaynak bakiye toplamıyla aynı.
- [ ] Cash/bank opening toplamları aynı.
- [ ] Unmatured security taşınır.
- [ ] Matured/closed security taşınmaz.
- [ ] Sequence MAX(id)+1 değerine alınır.
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
> CarryPeriod ve önizleme/erişim kopyalama akışını bu dosyadaki sıraya göre uygula. Kart/stock_balance ID sürekliliğini bozma; geçmiş hareket/belge taşıma; integrity:carry geçmeden source period'u kapatma.

## Kodlama öncesi blokaj — A-127

**[KARAR GEREKİYOR]** Açık satış/alış teklif-sipariş/taslak belgelerin source period kapanışından önce zorunlu kapatılması mı, yoksa target period'a taşınması mı gerektiği kilitli değildir. Source period closed olduktan sonra açık belgenin devam ettirilmesi mümkün olmayacağından bu karar kapanmadan CarryPeriod final uygulanmaz.
