# Şirketler arası kopyalama

## Yetki

Kaynak→hedef izinleri Master `company_copy_permissions` tablosundadır. Hedef şirkette çalışan kullanıcı yalnız izinli kaynakları görebilir.

## Bağlantı

Hedef aktif `period` bağlantısıdır. Kaynak şirketin **aynı yıl** period DB'si geçici `period_source` bağlantısına bağlanır. Global scope/withoutGlobalScopes kullanılmaz.

## Kopyalama

- yalnız kart ve karta ait gerekli yan kayıtlar kopyalanır,
- bakiye/hareket/belge kopyalanmaz,
- hedefte **yeni ID** oluşur,
- `source_company_id` ve `source_record_id` scalar provenance olarak saklanır; Master companies'a cross-DB FK değildir,
- canlı link/senkronizasyon yoktur.

## Kod çakışması

Hedefte aynı kod varsa otomatik overwrite veya `-2` üretilmez. Kullanıcıya:
1. mevcut hedef kartı kullan,
2. yeni bir hedef kod gir,
3. bu kartı kopyalamayı iptal et
seçenekleri gösterilir.

Seçim ve kopyalama period activity_log'a yazılır.
