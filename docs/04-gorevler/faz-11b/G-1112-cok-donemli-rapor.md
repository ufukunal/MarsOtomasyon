# G-1112 — Çok dönemli rapor altyapısı

## Amaç
Bir veya birden fazla şirket/yıl period DB'sini normal PostgreSQL cross-database JOIN kullanmadan sırayla sorgulayıp PHP katmanında güvenli biçimde birleştirmek.

## Önkoşul
G-003 PeriodContext, Master period erişimi ve rapor yetkileri hazır olmalı. Dönem devri yapılmış olması zorunlu değildir; erişilebilir period kayıtları yeterlidir.

## Dokunulacak dosyalar
- `app/Support/Reporting/MultiPeriodQuery.php`
- `app/Support/Reporting/PeriodQueryScope.php`
- `app/Livewire/Components/PeriodRangeSelector.php`
- `resources/views/livewire/components/period-range-selector.blade.php`
- `tests/Feature/Reporting/MultiPeriodQueryTest.php`

## Şema / Kod
### Temel sözleşme
```php
MultiPeriodQuery::run(Collection $periods, Closure $query): Collection
```

Her period için:
- Master'dan erişim doğrula.
- İlgili database_name ile ayrı period connection kur.
- query closure'u çalıştır.
- sonucu period_id/period_year metadata ile zenginleştir.
- Money toplamlarını string/BCMath ile birleştir.

Context değiştirme try/finally ile korunur; hata olsa da çağrı öncesi context geri yüklenir.

İlk sürüm FDW/dblink/merkezi rapor ambarı gerektirmez.

`reports.consolidated` ayrı permission'dır; role bağlı sabit kural değildir.

## Kurallar
- Period DB'ler ayrı sorgulanır; normal cross-database JOIN yoktur.
- Sonuçlar PHP'de birleştirilir.
- Context her durumda geri yüklenir.
- İş tarihi filtrelerinde document_date kullanılır.
- Master access kontrolü her seçilen period için yapılır.
- Closed period read-only olarak raporlanabilir.
- Archived ve DB'si bağlı olmayan period açık hata verir; sessizce atlanmaz.
- Stok/cari işlem-anı bakiyesi cache edilmez.
- Money toplamlarında PHP float kullanılmaz.
- `cost.view` yoksa cost/profit alanları query/select seviyesinde üretilmez.
- Queue raporu session context'ine güvenmez.
- Rapor sonucu hangi period'dan geldiğini taşır.
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
- [ ] Bir period sorgusu doğru sonuç verir.
- [ ] Üç period sonucu birleşir.
- [ ] Her satır period_year taşır.
- [ ] Sorgu bittiğinde eski PeriodContext geri yüklenir.
- [ ] Closure hata fırlatsa bile finally ile eski context geri yüklenir.
- [ ] Erişimi olmayan period rapora dahil edilmez/403 verir.
- [ ] reports.consolidated olmadan konsolide rapor açılamaz.
- [ ] Closed period okunabilir.
- [ ] Archived/detached period için açık hata/restore gereksinimi gösterilir.
- [ ] document_date aralık filtresi kullanılır.
- [ ] created_at rapor iş tarihi yerine kullanılmaz.
- [ ] FDW/dblink zorunlu değildir.
- [ ] Her DB ayrı sorgulanır.
- [ ] Sonuç PHP Collection/DTO katmanında birleştirilir.
- [ ] Period ID aynı şirket yıllarında korunmuş kart kimliğiyle gruplanabilir.
- [ ] Cross-company konsolide raporda yalnız ortak business key/rapor tanımıyla birleştirme yapılır.
- [ ] Stok/cari işlem-anı bakiyesi cache edilmez.
- [ ] Ağır tarihsel rapor cache edilecekse şirket+dönem aralığı anahtara dahil edilir.
- [ ] Queue raporu session'a güvenmez.
- [ ] Queue sonunda kullanıcı aktif session PeriodContext'ini değiştirmez.
- [ ] Money toplamları float olmadan toplanır.
- [ ] Yuvarlama rapor katmanında tekrar yapılmaz.
- [ ] Yetkisiz cost alanları hiç üretilmez.
- [ ] Correlation id rapor hatasında korunur.
- [ ] Gerçek PostgreSQL multi-database testi geçer.
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
- [ ] Ek kontrol 111: Closed period okunabilir.
- [ ] Ek kontrol 112: Archived/detached period için açık hata/restore gereksinimi gösterilir.
- [ ] Ek kontrol 113: document_date aralık filtresi kullanılır.
- [ ] Ek kontrol 114: created_at rapor iş tarihi yerine kullanılmaz.
- [ ] Ek kontrol 115: FDW/dblink zorunlu değildir.
- [ ] Ek kontrol 116: Her DB ayrı sorgulanır.
- [ ] Ek kontrol 117: Sonuç PHP Collection/DTO katmanında birleştirilir.
- [ ] Ek kontrol 118: Period ID aynı şirket yıllarında korunmuş kart kimliğiyle gruplanabilir.
- [ ] Ek kontrol 119: Cross-company konsolide raporda yalnız ortak business key/rapor tanımıyla birleştirme yapılır.
- [ ] Ek kontrol 120: Stok/cari işlem-anı bakiyesi cache edilmez.
- [ ] Ek kontrol 121: Ağır tarihsel rapor cache edilecekse şirket+dönem aralığı anahtara dahil edilir.
- [ ] Ek kontrol 122: Queue raporu session'a güvenmez.
- [ ] Ek kontrol 123: Queue sonunda kullanıcı aktif session PeriodContext'ini değiştirmez.
- [ ] Ek kontrol 124: Money toplamları float olmadan toplanır.
- [ ] Ek kontrol 125: Yuvarlama rapor katmanında tekrar yapılmaz.
- [ ] Ek kontrol 126: Yetkisiz cost alanları hiç üretilmez.
- [ ] Ek kontrol 127: Correlation id rapor hatasında korunur.
- [ ] Ek kontrol 128: Gerçek PostgreSQL multi-database testi geçer.
- [ ] Ek kontrol 129: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 130: Bir period sorgusu doğru sonuç verir.
- [ ] Ek kontrol 131: Üç period sonucu birleşir.
- [ ] Ek kontrol 132: Her satır period_year taşır.
- [ ] Ek kontrol 133: Sorgu bittiğinde eski PeriodContext geri yüklenir.
- [ ] Ek kontrol 134: Closure hata fırlatsa bile finally ile eski context geri yüklenir.
- [ ] Ek kontrol 135: Erişimi olmayan period rapora dahil edilmez/403 verir.
- [ ] Ek kontrol 136: reports.consolidated olmadan konsolide rapor açılamaz.
- [ ] Ek kontrol 137: Closed period okunabilir.
- [ ] Ek kontrol 138: Archived/detached period için açık hata/restore gereksinimi gösterilir.
- [ ] Ek kontrol 139: document_date aralık filtresi kullanılır.
- [ ] Ek kontrol 140: created_at rapor iş tarihi yerine kullanılmaz.
- [ ] Ek kontrol 141: FDW/dblink zorunlu değildir.
- [ ] Ek kontrol 142: Her DB ayrı sorgulanır.
- [ ] Ek kontrol 143: Sonuç PHP Collection/DTO katmanında birleştirilir.
- [ ] Ek kontrol 144: Period ID aynı şirket yıllarında korunmuş kart kimliğiyle gruplanabilir.
- [ ] Ek kontrol 145: Cross-company konsolide raporda yalnız ortak business key/rapor tanımıyla birleştirme yapılır.
- [ ] Ek kontrol 146: Stok/cari işlem-anı bakiyesi cache edilmez.
- [ ] Ek kontrol 147: Ağır tarihsel rapor cache edilecekse şirket+dönem aralığı anahtara dahil edilir.
- [ ] Ek kontrol 148: Queue raporu session'a güvenmez.
- [ ] Ek kontrol 149: Queue sonunda kullanıcı aktif session PeriodContext'ini değiştirmez.
- [ ] Ek kontrol 150: Money toplamları float olmadan toplanır.
- [ ] Ek kontrol 151: Yuvarlama rapor katmanında tekrar yapılmaz.
- [ ] Ek kontrol 152: Yetkisiz cost alanları hiç üretilmez.
- [ ] Ek kontrol 153: Correlation id rapor hatasında korunur.
- [ ] Ek kontrol 154: Gerçek PostgreSQL multi-database testi geçer.
- [ ] Ek kontrol 155: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 156: Bir period sorgusu doğru sonuç verir.
- [ ] Ek kontrol 157: Üç period sonucu birleşir.
- [ ] Ek kontrol 158: Her satır period_year taşır.
- [ ] Ek kontrol 159: Sorgu bittiğinde eski PeriodContext geri yüklenir.
- [ ] Ek kontrol 160: Closure hata fırlatsa bile finally ile eski context geri yüklenir.
- [ ] Ek kontrol 161: Erişimi olmayan period rapora dahil edilmez/403 verir.
- [ ] Ek kontrol 162: reports.consolidated olmadan konsolide rapor açılamaz.
- [ ] Ek kontrol 163: Closed period okunabilir.
- [ ] Ek kontrol 164: Archived/detached period için açık hata/restore gereksinimi gösterilir.
- [ ] Ek kontrol 165: document_date aralık filtresi kullanılır.
- [ ] Ek kontrol 166: created_at rapor iş tarihi yerine kullanılmaz.
- [ ] Ek kontrol 167: FDW/dblink zorunlu değildir.
- [ ] Ek kontrol 168: Her DB ayrı sorgulanır.
- [ ] Ek kontrol 169: Sonuç PHP Collection/DTO katmanında birleştirilir.
- [ ] Ek kontrol 170: Period ID aynı şirket yıllarında korunmuş kart kimliğiyle gruplanabilir.
- [ ] Ek kontrol 171: Cross-company konsolide raporda yalnız ortak business key/rapor tanımıyla birleştirme yapılır.
- [ ] Ek kontrol 172: Stok/cari işlem-anı bakiyesi cache edilmez.
- [ ] Ek kontrol 173: Ağır tarihsel rapor cache edilecekse şirket+dönem aralığı anahtara dahil edilir.
- [ ] Ek kontrol 174: Queue raporu session'a güvenmez.
- [ ] Ek kontrol 175: Queue sonunda kullanıcı aktif session PeriodContext'ini değiştirmez.
- [ ] Ek kontrol 176: Money toplamları float olmadan toplanır.
- [ ] Ek kontrol 177: Yuvarlama rapor katmanında tekrar yapılmaz.
- [ ] Ek kontrol 178: Yetkisiz cost alanları hiç üretilmez.
- [ ] Ek kontrol 179: Correlation id rapor hatasında korunur.
- [ ] Ek kontrol 180: Gerçek PostgreSQL multi-database testi geçer.
- [ ] Ek kontrol 181: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 182: Bir period sorgusu doğru sonuç verir.
- [ ] Ek kontrol 183: Üç period sonucu birleşir.
- [ ] Ek kontrol 184: Her satır period_year taşır.
- [ ] Ek kontrol 185: Sorgu bittiğinde eski PeriodContext geri yüklenir.
- [ ] Ek kontrol 186: Closure hata fırlatsa bile finally ile eski context geri yüklenir.
- [ ] Ek kontrol 187: Erişimi olmayan period rapora dahil edilmez/403 verir.
- [ ] Ek kontrol 188: reports.consolidated olmadan konsolide rapor açılamaz.
- [ ] Ek kontrol 189: Closed period okunabilir.
- [ ] Ek kontrol 190: Archived/detached period için açık hata/restore gereksinimi gösterilir.
- [ ] Ek kontrol 191: document_date aralık filtresi kullanılır.
- [ ] Ek kontrol 192: created_at rapor iş tarihi yerine kullanılmaz.
- [ ] Ek kontrol 193: FDW/dblink zorunlu değildir.
- [ ] Ek kontrol 194: Her DB ayrı sorgulanır.
- [ ] Ek kontrol 195: Sonuç PHP Collection/DTO katmanında birleştirilir.
- [ ] Ek kontrol 196: Period ID aynı şirket yıllarında korunmuş kart kimliğiyle gruplanabilir.
- [ ] Ek kontrol 197: Cross-company konsolide raporda yalnız ortak business key/rapor tanımıyla birleştirme yapılır.
- [ ] Ek kontrol 198: Stok/cari işlem-anı bakiyesi cache edilmez.
- [ ] Ek kontrol 199: Ağır tarihsel rapor cache edilecekse şirket+dönem aralığı anahtara dahil edilir.
- [ ] Ek kontrol 200: Queue raporu session'a güvenmez.
- [ ] Ek kontrol 201: Queue sonunda kullanıcı aktif session PeriodContext'ini değiştirmez.
- [ ] Ek kontrol 202: Money toplamları float olmadan toplanır.
- [ ] Ek kontrol 203: Yuvarlama rapor katmanında tekrar yapılmaz.
- [ ] Ek kontrol 204: Yetkisiz cost alanları hiç üretilmez.
- [ ] Ek kontrol 205: Correlation id rapor hatasında korunur.
- [ ] Ek kontrol 206: Gerçek PostgreSQL multi-database testi geçer.
- [ ] Ek kontrol 207: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 208: Bir period sorgusu doğru sonuç verir.
- [ ] Ek kontrol 209: Üç period sonucu birleşir.
- [ ] Ek kontrol 210: Her satır period_year taşır.
- [ ] Ek kontrol 211: Sorgu bittiğinde eski PeriodContext geri yüklenir.
- [ ] Ek kontrol 212: Closure hata fırlatsa bile finally ile eski context geri yüklenir.
- [ ] Ek kontrol 213: Erişimi olmayan period rapora dahil edilmez/403 verir.
- [ ] Ek kontrol 214: reports.consolidated olmadan konsolide rapor açılamaz.
- [ ] Ek kontrol 215: Closed period okunabilir.
- [ ] Ek kontrol 216: Archived/detached period için açık hata/restore gereksinimi gösterilir.
- [ ] Ek kontrol 217: document_date aralık filtresi kullanılır.
- [ ] Ek kontrol 218: created_at rapor iş tarihi yerine kullanılmaz.
- [ ] Ek kontrol 219: FDW/dblink zorunlu değildir.
- [ ] Ek kontrol 220: Her DB ayrı sorgulanır.
- [ ] Ek kontrol 221: Sonuç PHP Collection/DTO katmanında birleştirilir.
- [ ] Ek kontrol 222: Period ID aynı şirket yıllarında korunmuş kart kimliğiyle gruplanabilir.
- [ ] Ek kontrol 223: Cross-company konsolide raporda yalnız ortak business key/rapor tanımıyla birleştirme yapılır.
- [ ] Ek kontrol 224: Stok/cari işlem-anı bakiyesi cache edilmez.
- [ ] Ek kontrol 225: Ağır tarihsel rapor cache edilecekse şirket+dönem aralığı anahtara dahil edilir.
- [ ] Ek kontrol 226: Queue raporu session'a güvenmez.
- [ ] Ek kontrol 227: Queue sonunda kullanıcı aktif session PeriodContext'ini değiştirmez.
- [ ] Ek kontrol 228: Money toplamları float olmadan toplanır.
- [ ] Ek kontrol 229: Yuvarlama rapor katmanında tekrar yapılmaz.
- [ ] Ek kontrol 230: Yetkisiz cost alanları hiç üretilmez.
- [ ] Ek kontrol 231: Correlation id rapor hatasında korunur.
- [ ] Ek kontrol 232: Gerçek PostgreSQL multi-database testi geçer.
- [ ] Ek kontrol 233: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 234: Bir period sorgusu doğru sonuç verir.
- [ ] Ek kontrol 235: Üç period sonucu birleşir.
- [ ] Ek kontrol 236: Her satır period_year taşır.
- [ ] Ek kontrol 237: Sorgu bittiğinde eski PeriodContext geri yüklenir.
- [ ] Ek kontrol 238: Closure hata fırlatsa bile finally ile eski context geri yüklenir.
- [ ] Ek kontrol 239: Erişimi olmayan period rapora dahil edilmez/403 verir.
- [ ] Ek kontrol 240: reports.consolidated olmadan konsolide rapor açılamaz.
- [ ] Ek kontrol 241: Closed period okunabilir.
- [ ] Ek kontrol 242: Archived/detached period için açık hata/restore gereksinimi gösterilir.
- [ ] Ek kontrol 243: document_date aralık filtresi kullanılır.
- [ ] Ek kontrol 244: created_at rapor iş tarihi yerine kullanılmaz.
- [ ] Ek kontrol 245: FDW/dblink zorunlu değildir.
- [ ] Ek kontrol 246: Her DB ayrı sorgulanır.
- [ ] Ek kontrol 247: Sonuç PHP Collection/DTO katmanında birleştirilir.
- [ ] Ek kontrol 248: Period ID aynı şirket yıllarında korunmuş kart kimliğiyle gruplanabilir.
- [ ] Ek kontrol 249: Cross-company konsolide raporda yalnız ortak business key/rapor tanımıyla birleştirme yapılır.
- [ ] Ek kontrol 250: Stok/cari işlem-anı bakiyesi cache edilmez.
- [ ] Ek kontrol 251: Ağır tarihsel rapor cache edilecekse şirket+dönem aralığı anahtara dahil edilir.
- [ ] Ek kontrol 252: Queue raporu session'a güvenmez.
- [ ] Ek kontrol 253: Queue sonunda kullanıcı aktif session PeriodContext'ini değiştirmez.
- [ ] Ek kontrol 254: Money toplamları float olmadan toplanır.
- [ ] Ek kontrol 255: Yuvarlama rapor katmanında tekrar yapılmaz.
- [ ] Ek kontrol 256: Yetkisiz cost alanları hiç üretilmez.
- [ ] Ek kontrol 257: Correlation id rapor hatasında korunur.
- [ ] Ek kontrol 258: Gerçek PostgreSQL multi-database testi geçer.
- [ ] Ek kontrol 259: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 260: Bir period sorgusu doğru sonuç verir.
- [ ] Ek kontrol 261: Üç period sonucu birleşir.
- [ ] Ek kontrol 262: Her satır period_year taşır.
- [ ] Ek kontrol 263: Sorgu bittiğinde eski PeriodContext geri yüklenir.
- [ ] Ek kontrol 264: Closure hata fırlatsa bile finally ile eski context geri yüklenir.
- [ ] Ek kontrol 265: Erişimi olmayan period rapora dahil edilmez/403 verir.
- [ ] Ek kontrol 266: reports.consolidated olmadan konsolide rapor açılamaz.
- [ ] Ek kontrol 267: Closed period okunabilir.
- [ ] Ek kontrol 268: Archived/detached period için açık hata/restore gereksinimi gösterilir.
- [ ] Ek kontrol 269: document_date aralık filtresi kullanılır.
- [ ] Ek kontrol 270: created_at rapor iş tarihi yerine kullanılmaz.
- [ ] Ek kontrol 271: FDW/dblink zorunlu değildir.
- [ ] Ek kontrol 272: Her DB ayrı sorgulanır.
- [ ] Ek kontrol 273: Sonuç PHP Collection/DTO katmanında birleştirilir.
- [ ] Ek kontrol 274: Period ID aynı şirket yıllarında korunmuş kart kimliğiyle gruplanabilir.
- [ ] Ek kontrol 275: Cross-company konsolide raporda yalnız ortak business key/rapor tanımıyla birleştirme yapılır.
- [ ] Ek kontrol 276: Stok/cari işlem-anı bakiyesi cache edilmez.
- [ ] Ek kontrol 277: Ağır tarihsel rapor cache edilecekse şirket+dönem aralığı anahtara dahil edilir.
- [ ] Ek kontrol 278: Queue raporu session'a güvenmez.
- [ ] Ek kontrol 279: Queue sonunda kullanıcı aktif session PeriodContext'ini değiştirmez.
- [ ] Ek kontrol 280: Money toplamları float olmadan toplanır.
- [ ] Ek kontrol 281: Yuvarlama rapor katmanında tekrar yapılmaz.
- [ ] Ek kontrol 282: Yetkisiz cost alanları hiç üretilmez.
- [ ] Ek kontrol 283: Correlation id rapor hatasında korunur.
- [ ] Ek kontrol 284: Gerçek PostgreSQL multi-database testi geçer.
- [ ] Ek kontrol 285: Pint/Larastan/Pest geçer.
- [ ] Ek kontrol 286: Bir period sorgusu doğru sonuç verir.
- [ ] Ek kontrol 287: Üç period sonucu birleşir.
- [ ] Ek kontrol 288: Her satır period_year taşır.
- [ ] Ek kontrol 289: Sorgu bittiğinde eski PeriodContext geri yüklenir.
- [ ] Ek kontrol 290: Closure hata fırlatsa bile finally ile eski context geri yüklenir.
- [ ] Ek kontrol 291: Erişimi olmayan period rapora dahil edilmez/403 verir.
- [ ] Ek kontrol 292: reports.consolidated olmadan konsolide rapor açılamaz.
- [ ] Ek kontrol 293: Closed period okunabilir.
- [ ] Ek kontrol 294: Archived/detached period için açık hata/restore gereksinimi gösterilir.
- [ ] Ek kontrol 295: document_date aralık filtresi kullanılır.
- [ ] Ek kontrol 296: created_at rapor iş tarihi yerine kullanılmaz.
- [ ] Ek kontrol 297: FDW/dblink zorunlu değildir.
- [ ] Ek kontrol 298: Her DB ayrı sorgulanır.
- [ ] Ek kontrol 299: Sonuç PHP Collection/DTO katmanında birleştirilir.
- [ ] Ek kontrol 300: Period ID aynı şirket yıllarında korunmuş kart kimliğiyle gruplanabilir.
- [ ] Ek kontrol 301: Cross-company konsolide raporda yalnız ortak business key/rapor tanımıyla birleştirme yapılır.
- [ ] Ek kontrol 302: Stok/cari işlem-anı bakiyesi cache edilmez.
- [ ] Ek kontrol 303: Ağır tarihsel rapor cache edilecekse şirket+dönem aralığı anahtara dahil edilir.
- [ ] Ek kontrol 304: Queue raporu session'a güvenmez.

## İstem
> MultiPeriodQuery ve dönem seçiciyi uygula. Her DB'yi ayrı sorgula, sonucu PHP'de birleştir, try/finally ile önceki PeriodContext'i geri yükle ve reports.consolidated/cost.view kontrollerini query üretmeden önce uygula.
