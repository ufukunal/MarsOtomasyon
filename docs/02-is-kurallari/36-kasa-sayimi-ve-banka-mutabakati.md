# Kasa sayımı ve banka mutabakatı

## Kasa sayımı

K-094 gereği kupür bazlı sayım yoktur. Kullanıcı kasadaki toplam fiili bakiyeyi girer.

Confirm transaction'ında:

1. cash account kilitlenir,
2. system balance cash_movements toplamından yeniden hesaplanır,
3. actual balance kullanıcı girdisidir,
4. difference = actual - system BCMath ile hesaplanır,
5. fark 0 ise sayım confirmed olur, finans hareketi yoktur,
6. fark != 0 ise reason zorunludur,
7. kullanıcı onayıyla `cash_count_adjustment` document + tek cash movement oluşur,
8. post-write verify,
9. audit.

## Fark yönü

```
difference > 0 => cash movement in
difference < 0 => cash movement out
amount = abs(difference)
```

Geçmiş cash movement kayıtları değiştirilmez.

## Sayım tarihi

`count_date` iş tarihidir. Dönem kilidi aynı tarih üzerinden kontrol edilir.

Confirm edilmiş sayım immutable'dır.

## Eşzamanlılık

Sayım confirm edilirken kasa hesabı ve ilgili finans hareketi kaynağı kilitlenir. Draft açıldıktan sonra başka hareket oluşmuşsa eski ekrandaki system balance'a güvenilmez; confirm anında yeniden hesaplanır.

## Banka mutabakatı

K-095:

- ekstre dosya importu yok,
- otomatik eşleştirme yok,
- yeni finans hareketi üretmez,
- mevcut bank movement kullanıcı tarafından manuel değerlendirilir.

`is_reconciled`:

- null = henüz değerlendirilmemiş,
- true = mutabık,
- false = mutabık değil.

## Mutabakat güncellemesi

State-changing action:

- bank movement version kontrol eder,
- `is_reconciled`,
- `reconciled_at`,
- `reconciled_by + reconciled_by_name`

metadata'sını günceller,
- period audit yazar.

Finansal amount/direction/account/document alanları mutate edilmez.

Mutabakat durumu daha sonra yetkili kullanıcı tarafından yeniden değerlendirilebilir; her değişiklik audit history'de görünür.

## Yetki

Rol adı sabitlenmez. En az:

- `finance.cash_count`
- `finance.bank_reconcile`

izinleri kullanılır.

## Bütünlük

`integrity:finance`:

- cash_count difference ve adjustment hareketini,
- confirmed farkta adjustment_document varlığını,
- bank reconciliation actor/timestamp/status tutarlılığını

kontrol eder.

Fark otomatik düzeltilmez.
