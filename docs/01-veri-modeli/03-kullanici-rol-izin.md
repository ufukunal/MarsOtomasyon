# Kullanıcı, rol ve izin

spatie/laravel-permission kullanılır, **teams özelliği açık** ve takım
anahtarı `company_id`'dir. Böylece aynı kullanıcı farklı şirketlerde farklı
role sahip olabilir.

## users (Laravel varsayılanına ek)

```php
$table->boolean('is_active')->default(true);
$table->foreignId('last_company_id')->nullable()->constrained('companies');
```

`last_company_id` kullanıcının en son çalıştığı şirket; girişte oraya döner.

## company_user

```php
Schema::create('company_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['company_id', 'user_id']);
});
```

Kullanıcının hangi şirketlere erişebildiği. **Bu tabloda satırı olmayan
kullanıcı o şirketi göremez, şirket seçicide listelenmez.**

## Roller (seed)

| Rol | Kapsam |
|---|---|
| Yönetici | Her şey, dönem açma, kullanıcı yönetimi |
| Muhasebe | Cari, kasa, banka, çek, belge, rapor |
| Satış | Teklif, sipariş, irsaliye, fatura, cari görüntüleme |
| Satınalma | Satınalma siparişi, mal kabul, alış faturası, tedarikçi |
| Depo | Stok hareketleri, transfer, sayım, ambar fişi, sevkiyat |
| Üretim | Reçete, üretim emri, malzeme çıkışı, mamul girişi, fason |
| Görüntüleyici | Yalnızca okuma |

## İzinler

Her ekran için dört izin: `<ekran>.view`, `.create`, `.update`, `.cancel`.

**Ayrıca bağımsız bir izin:** `cost.view` — maliyet ve kâr görme.
Satış rolünde **yoktur**. Bu izin olmadan maliyet kolonları, kâr ve marj
alanları ekranda hiç render edilmez (gizlenmez, **basılmaz**).

## Örnek veri (seed)

- Kullanıcı: `admin@mars.local` / rol Yönetici / her iki şirkete bağlı
