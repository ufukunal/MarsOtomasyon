# İşlem geçmişi

## Neden kritik

Bu sistem stok, cari ve kasanın **tek kaydıdır**. Arkasında düzeltici bir
defter yoktur. Yanlış giren bir rakamı kimin ne zaman girdiğini gösteren
tek şey işlem geçmişidir.

## Loglanan

Kart oluşturma/değiştirme (eski + yeni değer), belge kesinleştirme, iptal ve
ters kayıt (gerekçeyle), dönem kapatma/açma, yetki değişikliği, maliyet sapma
uyarısının geçilmesi, şirketler arası kopyalama, giriş/çıkış/başarısız giriş.

## Loglanmayan

Liste görüntüleme, arama, rapor açma.

## Uygulama

```php
class Contact extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code','title','tax_number','term_days','discount_rate','risk_limit','is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

`company_id` her kayda otomatik yazılır (global observer).
