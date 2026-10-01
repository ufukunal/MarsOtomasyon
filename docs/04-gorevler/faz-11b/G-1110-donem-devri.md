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
6. Kartları FK dependency sırasıyla aynı ID/kodla kopyala.
7. Gerekli pasif kartları ilişki analiziyle dahil et.
8. StockBalance kimliklerini ve açılış miktarlarını hazırla.
9. Opening stock movements'i closing moving average maliyetiyle yaz.
10. product_costs kopyala.
11. cari/kasa/banka açılışlarını yaz.
12. vadesi gelmemiş çek/senetleri taşı.
13. Sequence'leri MAX(id)+1 ayarla.
14. integrity:carry çalıştır; farkta exception.
15. Kaynak period'u closed yap ve carry metadata yaz.
16. Transaction/business aşaması tamamlanınca kullanıcıya yetki devri ekranını aç.

### Actor
Period hareketlerindeki actor alanı Master user scalar id + user_name snapshot; cross-DB FK yok.

## Kurallar
- Aynı şirket dönem devrinde kartların ID ve kodları korunur.
- Taşınan stock_balance kayıtlarının ID'si korunur; stock_movements geçmişi taşınmaz.
- Sequence'ler kopya sonrası MAX(id)+1 seviyesine alınır.
- Aktif kartlar ile bakiye/hareket ilişkili gerekli pasif kartlar taşınır.
- Belgeler, açık teklif/sipariş, taslak, yoldaki transfer, karantina bekleyenler taşınmaz.
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
- [ ] Quarantine waiting hedefte yok.
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

### Claude inceleme matrisi
- [ ] Master/period/period_source connection sınırları doğru.
- [ ] Period company_id yok.
- [ ] Cross-DB FK yok.
- [ ] ID/kod carry kuralı doğru.
- [ ] StockBalance ID carry kuralı doğru.
- [ ] Sequence reset MAX(id)+1.
- [ ] FK dependency copy sırası doğru.
- [ ] Money/BCMath, float yok.
- [ ] Idempotency var.
- [ ] Transaction/failure state açık.
- [ ] Audit/correlation mevcut.
- [ ] integrity:carry mevcut.
- [ ] Period access doğrulanıyor.
- [ ] User permission copy carry sonrası.
- [ ] Gerçek PostgreSQL multi-db test var.
- [ ] Filament/Tailwind yok.
- [ ] Kapsam dışı yeni business karar yok.
- [ ] Ek kontrol 128: Pasif ama stok ilişkili ürün taşınır.
- [ ] Ek kontrol 129: Tamamen kullanılmayan pasif kart taşınmaz.
- [ ] Ek kontrol 130: StockBalance ID kaynakla aynı.
- [ ] Ek kontrol 131: Opening quantity kaynak kapanış quantity ile aynı.
- [ ] Ek kontrol 132: Opening unit cost kaynak moving average ile aynı.
- [ ] Ek kontrol 133: Geçmiş stock_movements hedefte yok.
- [ ] Ek kontrol 134: Documents hedefte yok.
- [ ] Ek kontrol 135: Open sales order/quote hedefte yok.
- [ ] Ek kontrol 136: Draft hedefte yok.
- [ ] Ek kontrol 137: In-transit transfer hedefte yok.
- [ ] Ek kontrol 138: Quarantine waiting hedefte yok.
- [ ] Ek kontrol 139: Contact opening toplamı kaynak bakiye toplamıyla aynı.
- [ ] Ek kontrol 140: Cash/bank opening toplamları aynı.
- [ ] Ek kontrol 141: Unmatured security taşınır.
- [ ] Ek kontrol 142: Matured/closed security taşınmaz.
- [ ] Ek kontrol 143: Sequence MAX(id)+1 değerine alınır.
- [ ] Ek kontrol 144: integrity:carry farkta source period'u kapatmaz.
- [ ] Ek kontrol 145: Başarılı carry source period'u closed yapar.
- [ ] Ek kontrol 146: carried_at metadata yazılır.
- [ ] Ek kontrol 147: İkinci aynı idempotency key çift carry üretmez.
- [ ] Ek kontrol 148: Unexpected failure target'ı yarım aktif bırakmaz.
- [ ] Ek kontrol 149: Yetki kopyalama carry bittikten sonra sorulur.
- [ ] Ek kontrol 150: Seçilmeyen kullanıcı yeni period erişimi almaz.
- [ ] Ek kontrol 151: Seçilen kullanıcı period_user_access kopyası alır.
- [ ] Ek kontrol 152: permission_overrides varsa kopyalanır.
- [ ] Ek kontrol 153: Role definitions Master'da tekrar yaratılmaz.
- [ ] Ek kontrol 154: Rollback yalnız target'ta iş kaydı yoksa destructive olabilir.
- [ ] Ek kontrol 155: Target'a iş kaydı girdiyse otomatik drop yapılmaz.
- [ ] Ek kontrol 156: Activity log actor id/name snapshot taşır.
- [ ] Ek kontrol 157: Cross-DB user FK yoktur.
- [ ] Ek kontrol 158: Gerçek PostgreSQL restore/migrate testi geçer.
- [ ] Ek kontrol 159: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 160: Target DB yoksa oluşturulur; varsa ikinci devir otomatik başlamaz.
- [ ] Ek kontrol 161: Target migration tam uygulanmadan copy başlamaz.
- [ ] Ek kontrol 162: Contact ID+code kaynakla aynı.
- [ ] Ek kontrol 163: Product ID+code kaynakla aynı.
- [ ] Ek kontrol 164: Location ID+code kaynakla aynı.
- [ ] Ek kontrol 165: Unit/category/brand/variant/set/config/price-list ID zinciri korunur.
- [ ] Ek kontrol 166: Foreign key dependency sırası kopyada korunur.
- [ ] Ek kontrol 167: Pasif ama açık bakiye ilişkili cari taşınır.
- [ ] Ek kontrol 168: Pasif ama stok ilişkili ürün taşınır.
- [ ] Ek kontrol 169: Tamamen kullanılmayan pasif kart taşınmaz.
- [ ] Ek kontrol 170: StockBalance ID kaynakla aynı.
- [ ] Ek kontrol 171: Opening quantity kaynak kapanış quantity ile aynı.
- [ ] Ek kontrol 172: Opening unit cost kaynak moving average ile aynı.
- [ ] Ek kontrol 173: Geçmiş stock_movements hedefte yok.
- [ ] Ek kontrol 174: Documents hedefte yok.
- [ ] Ek kontrol 175: Open sales order/quote hedefte yok.
- [ ] Ek kontrol 176: Draft hedefte yok.
- [ ] Ek kontrol 177: In-transit transfer hedefte yok.
- [ ] Ek kontrol 178: Quarantine waiting hedefte yok.
- [ ] Ek kontrol 179: Contact opening toplamı kaynak bakiye toplamıyla aynı.
- [ ] Ek kontrol 180: Cash/bank opening toplamları aynı.
- [ ] Ek kontrol 181: Unmatured security taşınır.
- [ ] Ek kontrol 182: Matured/closed security taşınmaz.
- [ ] Ek kontrol 183: Sequence MAX(id)+1 değerine alınır.
- [ ] Ek kontrol 184: integrity:carry farkta source period'u kapatmaz.
- [ ] Ek kontrol 185: Başarılı carry source period'u closed yapar.
- [ ] Ek kontrol 186: carried_at metadata yazılır.
- [ ] Ek kontrol 187: İkinci aynı idempotency key çift carry üretmez.
- [ ] Ek kontrol 188: Unexpected failure target'ı yarım aktif bırakmaz.
- [ ] Ek kontrol 189: Yetki kopyalama carry bittikten sonra sorulur.
- [ ] Ek kontrol 190: Seçilmeyen kullanıcı yeni period erişimi almaz.
- [ ] Ek kontrol 191: Seçilen kullanıcı period_user_access kopyası alır.
- [ ] Ek kontrol 192: permission_overrides varsa kopyalanır.
- [ ] Ek kontrol 193: Role definitions Master'da tekrar yaratılmaz.
- [ ] Ek kontrol 194: Rollback yalnız target'ta iş kaydı yoksa destructive olabilir.
- [ ] Ek kontrol 195: Target'a iş kaydı girdiyse otomatik drop yapılmaz.
- [ ] Ek kontrol 196: Activity log actor id/name snapshot taşır.
- [ ] Ek kontrol 197: Cross-DB user FK yoktur.
- [ ] Ek kontrol 198: Gerçek PostgreSQL restore/migrate testi geçer.
- [ ] Ek kontrol 199: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 200: Target DB yoksa oluşturulur; varsa ikinci devir otomatik başlamaz.
- [ ] Ek kontrol 201: Target migration tam uygulanmadan copy başlamaz.
- [ ] Ek kontrol 202: Contact ID+code kaynakla aynı.
- [ ] Ek kontrol 203: Product ID+code kaynakla aynı.
- [ ] Ek kontrol 204: Location ID+code kaynakla aynı.
- [ ] Ek kontrol 205: Unit/category/brand/variant/set/config/price-list ID zinciri korunur.
- [ ] Ek kontrol 206: Foreign key dependency sırası kopyada korunur.
- [ ] Ek kontrol 207: Pasif ama açık bakiye ilişkili cari taşınır.
- [ ] Ek kontrol 208: Pasif ama stok ilişkili ürün taşınır.
- [ ] Ek kontrol 209: Tamamen kullanılmayan pasif kart taşınmaz.
- [ ] Ek kontrol 210: StockBalance ID kaynakla aynı.
- [ ] Ek kontrol 211: Opening quantity kaynak kapanış quantity ile aynı.
- [ ] Ek kontrol 212: Opening unit cost kaynak moving average ile aynı.
- [ ] Ek kontrol 213: Geçmiş stock_movements hedefte yok.
- [ ] Ek kontrol 214: Documents hedefte yok.
- [ ] Ek kontrol 215: Open sales order/quote hedefte yok.
- [ ] Ek kontrol 216: Draft hedefte yok.
- [ ] Ek kontrol 217: In-transit transfer hedefte yok.
- [ ] Ek kontrol 218: Quarantine waiting hedefte yok.
- [ ] Ek kontrol 219: Contact opening toplamı kaynak bakiye toplamıyla aynı.
- [ ] Ek kontrol 220: Cash/bank opening toplamları aynı.
- [ ] Ek kontrol 221: Unmatured security taşınır.
- [ ] Ek kontrol 222: Matured/closed security taşınmaz.
- [ ] Ek kontrol 223: Sequence MAX(id)+1 değerine alınır.
- [ ] Ek kontrol 224: integrity:carry farkta source period'u kapatmaz.
- [ ] Ek kontrol 225: Başarılı carry source period'u closed yapar.
- [ ] Ek kontrol 226: carried_at metadata yazılır.
- [ ] Ek kontrol 227: İkinci aynı idempotency key çift carry üretmez.
- [ ] Ek kontrol 228: Unexpected failure target'ı yarım aktif bırakmaz.
- [ ] Ek kontrol 229: Yetki kopyalama carry bittikten sonra sorulur.
- [ ] Ek kontrol 230: Seçilmeyen kullanıcı yeni period erişimi almaz.
- [ ] Ek kontrol 231: Seçilen kullanıcı period_user_access kopyası alır.
- [ ] Ek kontrol 232: permission_overrides varsa kopyalanır.
- [ ] Ek kontrol 233: Role definitions Master'da tekrar yaratılmaz.
- [ ] Ek kontrol 234: Rollback yalnız target'ta iş kaydı yoksa destructive olabilir.
- [ ] Ek kontrol 235: Target'a iş kaydı girdiyse otomatik drop yapılmaz.
- [ ] Ek kontrol 236: Activity log actor id/name snapshot taşır.
- [ ] Ek kontrol 237: Cross-DB user FK yoktur.
- [ ] Ek kontrol 238: Gerçek PostgreSQL restore/migrate testi geçer.
- [ ] Ek kontrol 239: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 240: Target DB yoksa oluşturulur; varsa ikinci devir otomatik başlamaz.
- [ ] Ek kontrol 241: Target migration tam uygulanmadan copy başlamaz.
- [ ] Ek kontrol 242: Contact ID+code kaynakla aynı.
- [ ] Ek kontrol 243: Product ID+code kaynakla aynı.
- [ ] Ek kontrol 244: Location ID+code kaynakla aynı.
- [ ] Ek kontrol 245: Unit/category/brand/variant/set/config/price-list ID zinciri korunur.
- [ ] Ek kontrol 246: Foreign key dependency sırası kopyada korunur.
- [ ] Ek kontrol 247: Pasif ama açık bakiye ilişkili cari taşınır.
- [ ] Ek kontrol 248: Pasif ama stok ilişkili ürün taşınır.
- [ ] Ek kontrol 249: Tamamen kullanılmayan pasif kart taşınmaz.
- [ ] Ek kontrol 250: StockBalance ID kaynakla aynı.
- [ ] Ek kontrol 251: Opening quantity kaynak kapanış quantity ile aynı.
- [ ] Ek kontrol 252: Opening unit cost kaynak moving average ile aynı.
- [ ] Ek kontrol 253: Geçmiş stock_movements hedefte yok.
- [ ] Ek kontrol 254: Documents hedefte yok.
- [ ] Ek kontrol 255: Open sales order/quote hedefte yok.
- [ ] Ek kontrol 256: Draft hedefte yok.
- [ ] Ek kontrol 257: In-transit transfer hedefte yok.
- [ ] Ek kontrol 258: Quarantine waiting hedefte yok.
- [ ] Ek kontrol 259: Contact opening toplamı kaynak bakiye toplamıyla aynı.
- [ ] Ek kontrol 260: Cash/bank opening toplamları aynı.
- [ ] Ek kontrol 261: Unmatured security taşınır.
- [ ] Ek kontrol 262: Matured/closed security taşınmaz.
- [ ] Ek kontrol 263: Sequence MAX(id)+1 değerine alınır.
- [ ] Ek kontrol 264: integrity:carry farkta source period'u kapatmaz.
- [ ] Ek kontrol 265: Başarılı carry source period'u closed yapar.
- [ ] Ek kontrol 266: carried_at metadata yazılır.
- [ ] Ek kontrol 267: İkinci aynı idempotency key çift carry üretmez.
- [ ] Ek kontrol 268: Unexpected failure target'ı yarım aktif bırakmaz.
- [ ] Ek kontrol 269: Yetki kopyalama carry bittikten sonra sorulur.
- [ ] Ek kontrol 270: Seçilmeyen kullanıcı yeni period erişimi almaz.
- [ ] Ek kontrol 271: Seçilen kullanıcı period_user_access kopyası alır.
- [ ] Ek kontrol 272: permission_overrides varsa kopyalanır.
- [ ] Ek kontrol 273: Role definitions Master'da tekrar yaratılmaz.
- [ ] Ek kontrol 274: Rollback yalnız target'ta iş kaydı yoksa destructive olabilir.
- [ ] Ek kontrol 275: Target'a iş kaydı girdiyse otomatik drop yapılmaz.
- [ ] Ek kontrol 276: Activity log actor id/name snapshot taşır.
- [ ] Ek kontrol 277: Cross-DB user FK yoktur.
- [ ] Ek kontrol 278: Gerçek PostgreSQL restore/migrate testi geçer.
- [ ] Ek kontrol 279: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 280: Target DB yoksa oluşturulur; varsa ikinci devir otomatik başlamaz.
- [ ] Ek kontrol 281: Target migration tam uygulanmadan copy başlamaz.
- [ ] Ek kontrol 282: Contact ID+code kaynakla aynı.
- [ ] Ek kontrol 283: Product ID+code kaynakla aynı.
- [ ] Ek kontrol 284: Location ID+code kaynakla aynı.
- [ ] Ek kontrol 285: Unit/category/brand/variant/set/config/price-list ID zinciri korunur.
- [ ] Ek kontrol 286: Foreign key dependency sırası kopyada korunur.
- [ ] Ek kontrol 287: Pasif ama açık bakiye ilişkili cari taşınır.
- [ ] Ek kontrol 288: Pasif ama stok ilişkili ürün taşınır.
- [ ] Ek kontrol 289: Tamamen kullanılmayan pasif kart taşınmaz.
- [ ] Ek kontrol 290: StockBalance ID kaynakla aynı.
- [ ] Ek kontrol 291: Opening quantity kaynak kapanış quantity ile aynı.
- [ ] Ek kontrol 292: Opening unit cost kaynak moving average ile aynı.
- [ ] Ek kontrol 293: Geçmiş stock_movements hedefte yok.
- [ ] Ek kontrol 294: Documents hedefte yok.
- [ ] Ek kontrol 295: Open sales order/quote hedefte yok.
- [ ] Ek kontrol 296: Draft hedefte yok.
- [ ] Ek kontrol 297: In-transit transfer hedefte yok.
- [ ] Ek kontrol 298: Quarantine waiting hedefte yok.
- [ ] Ek kontrol 299: Contact opening toplamı kaynak bakiye toplamıyla aynı.
- [ ] Ek kontrol 300: Cash/bank opening toplamları aynı.
- [ ] Ek kontrol 301: Unmatured security taşınır.
- [ ] Ek kontrol 302: Matured/closed security taşınmaz.
- [ ] Ek kontrol 303: Sequence MAX(id)+1 değerine alınır.
- [ ] Ek kontrol 304: integrity:carry farkta source period'u kapatmaz.

## İstem
> CarryPeriod ve önizleme/erişim kopyalama akışını bu dosyadaki sıraya göre uygula. Kart/stock_balance ID sürekliliğini bozma; geçmiş hareket/belge taşıma; integrity:carry geçmeden source period'u kapatma.
