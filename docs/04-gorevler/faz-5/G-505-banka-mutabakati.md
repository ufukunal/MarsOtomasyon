# G-505 — Manuel banka mutabakatı

## Amaç

K-095'e göre mevcut bank movement kayıtlarını manuel mutabık / mutabık değil olarak değerlendirmek.

## Önkoşul

G-501.

## Dokunulacak dosyalar

- BankReconciliation liste bileşeni
- `SetBankMovementReconciliation` Action
- reconciliation permission/policy
- banka mutabakat testleri

## Şema / Kod

Mutabakat metadata:

- is_reconciled nullable bool
- reconciled_at
- reconciled_by
- reconciled_by_name
- version

Durum:

- null: değerlendirilmemiş
- true: mutabık
- false: mutabık değil

## Kurallar

- Dosya importu yok.
- Otomatik eşleştirme yok.
- Mutabakat yeni bank movement üretmez.
- amount/direction/account/document/contact mutate edilmez.
- Her durum değişikliği period audit'e yazılır.
- Optimistic lock zorunlu.

## Kabul ölçütü

- Kullanıcı hareketi mutabık işaretleyebiliyor.
- Mutabık değil işaretleyebiliyor.
- Değerlendirmeyi temizleyebiliyor.
- Finansal hareket içeriği değişmiyor.
- Stale version update reddediliyor.
- Yetkisiz kullanıcı durum değiştiremiyor.
- Audit önceki/yeni durum + actor taşıyor.
- Repo'ya ekstre parser/import route'u eklenmiyor.

## İstem

> K-095 manuel mutabakatı mevcut bank_movements üzerinde metadata olarak uygula. Ekstre importu veya otomatik matching ekleme.
