# G-408 — Alış ters kayıt ve bütünlük

## Amaç

Posted alış belgelerinin immutable kalmasını, purchase_invoice ters etkilerini ve Faz 4 bütünlük kontrollerini tamamlamak.

## Önkoşul

G-311 ters kayıt altyapısı, G-401…G-407.

## Dokunulacak dosyalar

- mevcut reverse Action genişletmeleri
- purchase invoice reversal doğrulaması
- `IntegrityPurchasing` komutu
- `IntegrityDocuments` / `IntegrityStock` Faz 4 kapsam genişletmeleri
- reversal/integrity testleri

## Şema / Kod

Purchase invoice reverse:

- yeni purchase_invoice reversal document,
- `reversal_of` relation,
- original purchase stock in için inverse stock out,
- supplier credit için debit,
- maliyet/stock post-write verify.

Goods receipt reversal operasyon fulfillment'ını tersler; stok/cari/maliyet üretmez.

## Kurallar

- Original mutate edilmez.
- Aynı original ikinci kez reverse edilemez.
- Reversed child partial toplamından çıkar.
- Reverse effect aynı transaction/idempotency disiplinine uyar.
- Bütünlük farkları otomatik düzeltilmez.

## Kabul ölçütü

- Purchase invoice reverse stock ve cari net etkisini tersliyor.
- Original belge değişmiyor.
- Duplicate reverse reddediliyor.
- Reversed goods receipt order remaining'i geri açıyor ama stock yazmıyor.
- integrity:purchasing selection, lineage, quantity ve effect matrix bozukluklarını raporluyor.
- integrity:stock purchase movement unit_cost/total_cost tutarsızlığını yakalıyor.

## İstem

> Mevcut G-311 reverse altyapısını Faz 4 belge tiplerine genişlet. Orijinali değiştirme; inverse etkileri yeni kayıtlarla üret ve bütünlük komutlarını güncelle.
