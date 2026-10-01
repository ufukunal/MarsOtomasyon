# G-111 — Şirketler arası kopyalama ekranı

## Amaç
İzinli olduğu şirketten cari veya ürün kartı kopyalama. **Kopya kayıt
oluşur, canlı bağ kurulmaz.**

## Önkoşul
G-004 (company_copy_permissions), G-104, G-106


## Dokunulacak dosyalar
- Görevde tarif edilen migration/model/action/Livewire/test dosyaları; kapsam dışına çıkma.


## Şema / Kod

Bu bölümdeki mevcut şema örnekleri aşağıdaki kanonik mimari kurallarla birlikte uygulanır. Çelişkide kanonik kurallar üstündür.

## Akış

1. Kullanıcı hedef şirkette "Başka Şirketten Aktar" ekranını açar
2. İzinli kaynak şirketler listelenir (`company_copy_permissions` üzerinden)
3. Kaynak ve tür (cari / ürün) seçilir
4. Kaynak şirketin kartları listelenir
5. Seçilen kartlar kopyalanır

## Action

```php
final class CopyRecordsBetweenCompanies
{
    public function handle(int $sourceCompanyId, CompanyCopyType $type, array $ids): CopyResult
    {
        abort_unless(
            CompanyCopyPermission::allows($sourceCompanyId, CompanyContext::id(), $type),
            403, 'Bu şirketten veri aktarma izniniz yok.'
        );

        // kaynak kayıtları SADECE burada period_source bağlantısından oku
        $records = $type === CompanyCopyType::Contact
            ? Contact::on('period_source')->whereIn('id', $ids)->get()
            : Product::on('period_source')->whereIn('id', $ids)->get();

        // her biri için: yeni kayıt, hedef source_* doldur,
        // kod çakışırsa -2 ekle, sonucu raporla
    }
}
```

## Kurallar
- İzin kontrolü **ilk satır**; izinsizse 403
- `on('period_source')` yalnızca burada kullanılır
- Kopyalanan kayıtta `source_company_id` ve `source_record_id` dolar
- Bakiye, hareket, belge **kopyalanmaz** — yalnızca kart bilgisi
- Kod çakışırsa `-2`, `-3` eklenir ve sonuç raporunda bildirilir
- `audit_log`'a kayıt düşer

## Güncelleme kontrolü
"Kaynakta değişti mi" işlemi kopyalanan kayıtları kaynakla karşılaştırır,
farkı listeler; kullanıcı isterse günceller. **Otomatik güncelleme yoktur.**


### Göreve özel kararlar
- Master `company_copy_permissions` kaynak→hedef izni tutar.
- Kaynak aynı yıl period DB `period_source` ile okunur; `withoutGlobalScopes` kullanılmaz.
- Hedef yeni ID üretir.
- Kod çakışmasında kullanıcı: mevcut kart / yeni kod / iptal; otomatik -2 veya overwrite yok.


### Uygulama ayrıntıları
- Kaynak kart aynı yılın kaynak period DB'sinden `period_source` bağlantısıyla okunur.
- Master `company_copy_permissions` kaynak→hedef iznini doğrular; `withoutGlobalScopes` kullanılmaz.
- Hedef kart yeni ID alır; `source_company_id + source_record_id` provenance olarak saklanır.
- Hedefte kod çakışırsa kullanıcıya mevcut kartı kullan / yeni kod gir / iptal seçenekleri sunulur; otomatik overwrite veya `-2` yoktur.

## Kabul ölçütü
- İzin yokken ekran açılmıyor, doğrudan istek 403
- Kopyalanan kayıt hedef şirkette görünüyor, kaynakta değişiklik olmuyor
- Kod çakışması `-2` ile çözülüyor ve raporlanıyor
- Kaynak kayıt sonradan değişince hedef **değişmiyor**


## İstem
> CopyRecordsBetweenCompanies action'ını ve "Başka Şirketten Aktar" Livewire
> ekranını yaz. İzin kontrolü action'ın ilk satırı olsun.
> Kaynak kartları `period_source` bağlantısından oku; `withoutGlobalScopes` veya company_id filtresi kullanma. İzni önce Master `company_copy_permissions` üzerinden doğrula.
> Kod çakışmasını -2 ekleyerek çöz ve sonuç raporunda bildir.
