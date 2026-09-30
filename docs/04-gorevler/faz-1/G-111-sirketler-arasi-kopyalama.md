# G-111 — Şirketler arası kopyalama ekranı

## Amaç
İzinli olduğu şirketten cari veya ürün kartı kopyalama. **Kopya kayıt
oluşur, canlı bağ kurulmaz.**

## Önkoşul
G-004 (company_links), G-104, G-106

## Akış

1. Kullanıcı hedef şirkette "Başka Şirketten Aktar" ekranını açar
2. İzinli kaynak şirketler listelenir (`company_links` üzerinden)
3. Kaynak ve tür (cari / ürün) seçilir
4. Kaynak şirketin kartları listelenir
5. Seçilen kartlar kopyalanır

## Action

```php
final class CopyRecordsBetweenCompanies
{
    public function handle(int $sourceCompanyId, CompanyLinkType $type, array $ids): CopyResult
    {
        abort_unless(
            CompanyLink::allows($sourceCompanyId, CompanyContext::id(), $type),
            403, 'Bu şirketten veri aktarma izniniz yok.'
        );

        // kaynak kayıtları SADECE burada global scope dışına çıkarak oku
        $records = $type === CompanyLinkType::Contact
            ? Contact::withoutGlobalScopes()->where('company_id', $sourceCompanyId)->whereIn('id', $ids)->get()
            : Product::withoutGlobalScopes()->where('company_id', $sourceCompanyId)->whereIn('id', $ids)->get();

        // her biri için: yeni kayıt, hedef company_id, source_* doldur,
        // kod çakışırsa -2 ekle, sonucu raporla
    }
}
```

## Kurallar
- İzin kontrolü **ilk satır**; izinsizse 403
- `withoutGlobalScopes()` yalnızca burada kullanılır
- Kopyalanan kayıtta `source_company_id` ve `source_record_id` dolar
- Bakiye, hareket, belge **kopyalanmaz** — yalnızca kart bilgisi
- Kod çakışırsa `-2`, `-3` eklenir ve sonuç raporunda bildirilir
- `audit_log`'a kayıt düşer

## Güncelleme kontrolü
"Kaynakta değişti mi" işlemi kopyalanan kayıtları kaynakla karşılaştırır,
farkı listeler; kullanıcı isterse günceller. **Otomatik güncelleme yoktur.**

## Kabul ölçütü
- İzin yokken ekran açılmıyor, doğrudan istek 403
- Kopyalanan kayıt hedef şirkette görünüyor, kaynakta değişiklik olmuyor
- Kod çakışması `-2` ile çözülüyor ve raporlanıyor
- Kaynak kayıt sonradan değişince hedef **değişmiyor**

## İstem
> CopyRecordsBetweenCompanies action'ını ve "Başka Şirketten Aktar" Livewire
> ekranını yaz. İzin kontrolü action'ın ilk satırı olsun.
> withoutGlobalScopes yalnızca izin doğrulandıktan sonra kullanılsın.
> Kod çakışmasını -2 ekleyerek çöz ve sonuç raporunda bildir.
