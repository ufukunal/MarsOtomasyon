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


### Uygulama ayrıntıları
- İlk sürümde period DB'ler ayrı ayrı sorgulanır; normal cross-database JOIN/FDW/dblink zorunlu değildir.
- Her sorgu sonucu period_id/year metadata taşır ve sonuçlar PHP katmanında birleştirilir.
- Seçilen her period için Master erişim yetkisi doğrulanır; `reports.consolidated` ayrıca kontrol edilir.
- Context değişimi `try/finally` ile geri yüklenir; rapor çağrısı aktif kullanıcı period context'ini bozamaz.
- Money toplamları float kullanılmadan birleştirilir; `cost.view` yoksa maliyet/kâr alanları query üretiminde dışarıda bırakılır.

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

## İstem
> MultiPeriodQuery ve dönem seçiciyi uygula. Her DB'yi ayrı sorgula, sonucu PHP'de birleştir, try/finally ile önceki PeriodContext'i geri yükle ve reports.consolidated/cost.view kontrollerini query üretmeden önce uygula.
