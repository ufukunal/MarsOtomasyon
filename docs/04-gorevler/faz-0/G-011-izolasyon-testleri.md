# G-011 — İzolasyon ve temel testler

**Not:** dönem verisi ayrı veritabanında olduğu için fiziksel olarak
izoledir. Test edilecek asıl yer **master kartlarıdır** (global scope) ve
**bağlantı değişiminin doğruluğudur**.

## Amaç
Şirket izolasyonunun gerçekten çalıştığını kanıtlamak. **Bu görev
atlanamaz** — izolasyon sessizce bozulur, testsiz fark edilmez.

## Önkoşul
G-002 … G-010


## Dokunulacak dosyalar
- Bu görev için mevcut metinde tanımlanan uygulama/migration/test dosyaları; kapsam dışı dosyaya dokunma.


## Şema / Kod

Bu görevde aşağıdaki mevcut kod/şema örnekleri normatiftir. Yeni tablo gerekmiyorsa migration ekleme; mevcut mimari sözleşmeyi bozacak ek şema uydurma.

## Yazılacak testler

### 0. Bağlantı değişimi
- `PeriodContext::use(A, 2026)` sonrası dönem sorguları `ABCHolding_2026`'ya gidiyor
- `use(B, 2026)` sonrası `XYZltd_2026`'ya gidiyor
- Dönem seçilmeden dönem modeline erişim `NoActivePeriodException`

### 1. Master kart izolasyonu
```php
it('bir şirketin kaydı diğer şirkette görünmez', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();

    CompanyContext::set($a->id);
    $series = NumberSeries::create([
        'document_type' => 'quote', 'prefix' => 'TKL',
        'year' => 2026, 'last_number' => 0,
    ]);

    CompanyContext::set($b->id);
    expect(NumberSeries::find($series->id))->toBeNull();
});
```

### 2. company_id otomatik dolma
Kayıt oluştururken `company_id` verilmese de aktif şirketle dolmalı.

### 3. Numara üretimi
- Peş peşe üç çağrı: `00001`, `00002`, `00003`
- İki farklı şirkette sayaçlar bağımsız
- Transaction geri alınırsa numara artmaz

### 4. Dönem kilidi
- Açık dönem / satırı olmayan ay: istisna yok
- Kapalı dönem: `PeriodClosedException`

### 5. Yetki
- Satış rolü `cost.view` iznine sahip **değil**
- Yönetici sahip

### 6. Kopyalama izni
- `company_copy_permissions` kaydı yokken `allows()` false
- İzinsiz kopyalama denemesi 403

### 7. Dosya ekleri
- İzinsiz tür reddedilir
- Kayıt silinince dosya diskten kalkar

### 8. Yazdırma profili çözümleme
- Kullanıcı+makine profili varsa o seçilir
- Yoksa kullanıcı profili, o da yoksa şirket varsayılanı


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- İzolasyon company_id scope ile değil iki ayrı period DB ile test edilir.
- Master dönem erişimi olmadan PeriodContext kurulamadığını test et.


### Uygulama ayrıntıları
- Test fixture en az iki şirket ve her biri için ayrı period DB oluşturur.
- Aynı tablo/aynı ID farklı period DB'lerde bulunabilmeli; yanlış context veri sızıntısı üretmemeli.
- `company_user` veya `period_user_access` eksikse PeriodContext kurulması reddedilir.
- Testler global scope davranışını değil fiziksel DB seçim izolasyonunu doğrular.

## Kabul ölçütü
```bash
./vendor/bin/pest          # hepsi yeşil
./vendor/bin/pint --test   # temiz
./vendor/bin/phpstan analyse
```


## İstem
> Yukarıdaki sekiz başlık için Pest testleri yaz. Company factory'si oluştur.
> Testler gerçek veritabanına karşı çalışsın (RefreshDatabase). Özellikle
> izolasyon testini atlama.
