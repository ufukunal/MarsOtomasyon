# G-212 — Faz 2 testleri

## Amaç
Stok ve maliyet hesabının doğruluğunu kanıtlamak. **Buradaki bir hata
her rakama yayılır**, o yüzden testler kapsamlı olmalı.

## Maliyet testleri

```php
it('hareketli ortalamayi dogru hesaplar', function () {
    giris(10, 100);              // ortalama 100
    giris(10, 200);              // ortalama 150
    expect(ortalama())->toBe(150.0);

    cikis(5);                    // ortalama DEĞİŞMEZ
    expect(ortalama())->toBe(150.0);
    expect(sonCikisMaliyeti())->toBe(150.0);
});
```

- Stok 0'ken giriş: giren fiyat maliyet olur
- Transfer: ortalama değişmez, iki hareket aynı maliyetle
- %30 sapmada uyarı, %20'de yok
- Uyarı geçilince `activity_log` kaydı

## Stok testleri

- Giriş bakiyeyi artırır, çıkış azaltır
- `balance_after` her harekette doğru
- Negatif stok izinsizken `NegativeStockException`
- İzinliyken geçer
- Kapalı dönemde `PeriodClosedException`
- **Eşzamanlılık:** 10 paralel çıkış sonrası bakiye tutarlı
- `stock:verify` fark bulmuyor

## Kullanılabilir hesabı

- Rezerve, konsinye ve karantina kullanılabilirden düşülüyor
- Fiziksel stok değişmiyor

## Sayım

- `system_quantity` sayım başlarken donuyor
- Sayım sırasındaki satış dondurulan değeri değiştirmiyor
- Onaylanmayan satır için hareket yok
- Onaylı satır bakiyeyi sayılan miktara eşitliyor

## Transfer

- Kaynak azalır, hedef teslim alınana kadar değişmez
- Kısmi teslim kalanı doğru takip eder
- Aynı lokasyona transfer reddedilir

## Karantina

- Karantinaya giriş kullanılabiliri azaltır, stoğu değiştirmez
- Satılabilir kararında hareket oluşmaz
- Hurda kararında çıkış hareketi oluşur

## İzolasyon

- A şirketinin stok hareketi B şirketinde görünmez
- A şirketinin ürününe B şirketinden hareket yazılamaz

## Kabul ölçütü
```bash
./vendor/bin/pest --coverage   # stok ve maliyet sınıflarında %90+
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```

## İstem
> Yukarıdaki başlıkların her biri için Pest testi yaz. Eşzamanlılık testini
> atlama (paralel çıkış denemesi). Maliyet testlerinde beklenen değerleri
> elle hesaplayıp doğrula. RefreshDatabase kullan.
