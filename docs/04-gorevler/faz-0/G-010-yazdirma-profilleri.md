# G-010 — Yazdırma profilleri ve soyutlama

## Amaç
"Hangi iş hangi yazıcıya gitsin" ayarı ve taşıyıcıdan bağımsız yazdırma
arayüzü. İleride özel tarayıcı kabuğu veya yerel ajan takılacak.

## Önkoşul
G-003


## Dokunulacak dosyalar
- `app/Support/Printing/PrintManager.php`

## Şema / Kod
```php
Schema::connection('master')->create('print_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
    $table->foreignId('user_id')->nullable()->constrained();
    $table->string('machine_key', 64)->nullable();
    $table->string('print_type', 30);
    $table->string('printer_name')->nullable();
    $table->string('paper_code', 20)->nullable();
    $table->decimal('width_mm', 8, 2)->nullable();
    $table->decimal('height_mm', 8, 2)->nullable();
    $table->jsonb('settings')->nullable(); // dpi, gap, darkness vb.
    $table->unsignedBigInteger('template_id')->nullable();
    $table->timestamps();
    $table->unique(['company_id','user_id','machine_key','print_type'], 'print_profiles_unique');
});
```

## Enum

```php
enum PrintType: string
{
    case A4           = 'a4';
    case ProductLabel = 'product_label';
    case CartonLabel  = 'carton_label';
    case Receipt      = 'receipt';
    case Report       = 'report';
}
```

## PrintManager

`app/Support/Printing/PrintManager.php`

```php
final class PrintManager
{
    public static function send(PrintType $type, array $payload): PrintResult
    {
        $profile = static::resolveProfile($type);
        $driver  = app(config('printing.driver_class'));

        return $driver->send($type, $payload, $profile);
    }

    private static function resolveProfile(PrintType $type): ?PrintProfile
    {
        $companyId = PeriodContext::companyId();
        $userId    = auth()->id();
        $machine   = request()->cookie('machine_key');

        return PrintProfile::query()
            ->where('company_id', $companyId)
            ->where('print_type', $type->value)
            ->where(fn ($q) => $q
                ->where(fn ($s) => $s->where('user_id', $userId)->where('machine_key', $machine))
                ->orWhere(fn ($s) => $s->where('user_id', $userId)->whereNull('machine_key'))
                ->orWhere(fn ($s) => $s->whereNull('user_id')))
            ->orderByRaw('user_id IS NULL, machine_key IS NULL')
            ->first();
    }
}
```

## Sürücüler

`PrintDriver` arayüzü: `send(PrintType $type, array $payload, ?PrintProfile $profile): PrintResult`

Faz 0'da yalnızca `BrowserDriver` yazılır — PDF üretir, tarayıcıya verir.
`AgentDriver` ve `ShellDriver` ileride eklenecek; arayüz şimdiden hazır.

## config/printing.php

```php
return [
    'driver'       => env('PRINTING_DRIVER', 'browser'),
    'driver_class' => \App\Support\Printing\Drivers\BrowserDriver::class,
];
```

## Kritik kural
Uygulamanın hiçbir yerinden doğrudan yazıcıya erişilmez.
**Tek giriş noktası `PrintManager::send()`.**


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- print_profiles Master DB'dedir.
- Unique kapsam company+user+machine+print_type.
- Ölçü paper_code + width_mm + height_mm; printer_name yalnız cihaz eşleme.


### Uygulama ayrıntıları
- `print_profiles` Master DB'dedir; şirket + kullanıcı + makine + çıktı tipi kapsamında çözülür.
- Temel ölçü `paper_code`, `width_mm`, `height_mm`; DPI/gap/darkness gibi cihaz ayarları `settings` JSON'da tutulabilir.
- `printer_name` yalnız fiziksel cihaz eşlemesidir; ERP mantığı marka/model bağımsızdır.
- Uygulama yazıcıya doğrudan erişmez; tek giriş `PrintManager::send()` olur.

## Kabul ölçütü
- Profil çözümleme doğru sırayla çalışır (kullanıcı+makine → kullanıcı → şirket)
- Profil yoksa sistem varsayılanı döner, hata vermez
- `BrowserDriver` PDF üretir


## İstem
> print_profiles tablosu için migration, PrintProfile modeli, PrintType enum'u,
> PrintDriver arayüzü, BrowserDriver sınıfı, PrintManager sınıfı ve
> config/printing.php dosyasını yaz. Profil çözümleme sırası:
> kullanıcı+makine, kullanıcı, şirket varsayılanı. AgentDriver veya
> ShellDriver YAZMA, yalnız arayüzü hazırla.
