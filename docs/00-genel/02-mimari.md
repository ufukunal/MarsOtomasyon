# Mimari

## Katmanlar

```
Livewire bileşeni → Action (iş kuralı) → Model → Veritabanı
                        ↓
                  Event / Listener → audit, stok, cari hareketi
```

**Kural:** iş kuralı Livewire bileşenine yazılmaz. Bileşen girdi toplar,
bir Action çağırır. Böylece aynı kural ekrandan, içe aktarmadan, testten
aynı şekilde çalışır.

## Dizin düzeni

```
app/
  Actions/
    Numbering/      numara üretimi
    Periods/        dönem kontrolü
    Companies/      şirketler arası kopyalama
  Models/
  Livewire/
    Pages/          tam sayfa ekranlar
    Components/     tekrar kullanılan parçalar
  Support/
    Company/        CompanyContext, BelongsToCompany
    Printing/       PrintManager ve taşıyıcılar
  Enums/
  Policies/
resources/
  css/app.css       TEK tema dosyası
  js/app.js         asgari JS
  views/layouts/ , views/livewire/
```

## Şirket izolasyonu

Aktif şirket `session('active_company_id')`, `CompanyContext` üzerinden okunur.

`BelongsToCompany` trait'i modele iki şey ekler:
1. Global scope — her sorguya `where company_id = aktif şirket`
2. `creating` olayında `company_id` otomatik doldurma

**Trait'i eklemeyi unutmak veri sızıntısıdır.** Her yeni model için izolasyon
testi yazılır (G-011).

## Yazdırma soyutlaması

Uygulama doğrudan yazıcıya konuşmaz. `PrintManager::send($type, $payload)`
çağrılır; taşıyıcı (tarayıcı / yerel ajan / özel kabuk) tek sınıfta değişir.
