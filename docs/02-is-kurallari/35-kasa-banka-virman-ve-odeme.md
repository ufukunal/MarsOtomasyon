# Kasa/Banka virman ve tedarikçi ödeme

## Virman

K-092 gereği desteklenen kombinasyonlar:

- kasa → kasa,
- kasa → banka,
- banka → kasa,
- banka → banka.

Kaynak ve hedef hesabın `currency` değeri aynı olmalıdır. Kaynak ve hedef aynı fiziksel hesap olamaz.

Virman:

- cari hareket üretmez,
- stok hareketi üretmez,
- kur dönüşümü üretmez,
- kur farkı üretmez.

## finance_transfer posting

Tek `finance_transfer` document iki finans hareketinin ortak kimliğidir.

Transaction:

1. idempotency key doğrula,
2. `EnsurePeriodOpen(document_date)`,
3. source/target hesapları deterministik sırayla kilitle,
4. source != target doğrula,
5. currency eşitliğini doğrula,
6. numara üret,
7. source için `out`,
8. target için aynı tutarda `in`,
9. post-write verify,
10. audit,
11. idempotency done.

İki hareket tek transaction içinde oluşur; biri yazılıp diğeri yazılamaz.

## Finans hesabı bakiyesi

Kasa:

```
balance = SUM(in) - SUM(out)
```

Banka:

```
balance = SUM(in) - SUM(out)
```

Bakiye hesap kartında cache edilmez.

## Tedarikçi ödeme

K-093 ve K-062:

- ana işlem genel **Tedarikçi Ödeme** formudur,
- alış faturası ekranında `Ödeme Yap` kısayolu vardır,
- kaynak alış faturası opsiyoneldir,
- kaynak ilişkisi bilgi amaçlıdır,
- ödeme belirli faturayı kalıcı settlement ile kapatmaz,
- kısmi ödeme serbesttir.

## supplier_payment posting

Faz 4 alış faturası supplier borcunu `contact_transactions.credit` ile oluşturur.

Tedarikçi ödeme:

- supplier contact için `contact_transactions.debit`,
- seçilen cash/bank hesap için `out`

üretir.

Böylece:

```
supplier balance = debit - credit
```

formülünde ödeme negatif borcu sıfıra yaklaştırır.

## Para birimi

K-013 genişletilmez.

- Faz 5 supplier_payment ledger etkisi şirket temel para birimindedir.
- Finans hesabı hareketi yeni bir FX dönüşümü veya kur farkı hesabı üretmez.
- Farklı para birimleri arasında ödeme/virman dönüşüm motoru kurulmaz.
- Dövizli alış faturası cari borcu zaten Faz 4 posting'inde frozen kurla temel para birimine çevrilmiştir.

## Fatura kısayolu

Alış faturası ekranındaki `Ödeme Yap`:

- supplier contact'ı hazır getirir,
- kaynak purchase_invoice relation'ını bilgi amaçlı hazırlar,
- tutar kullanıcı tarafından değiştirilebilir,
- invoice remaining diye kalıcı settlement alanı üretmez.

## Ters kayıt

Posted finance_transfer veya supplier_payment mutate edilmez.

Reverse:

- yeni ters document/hareketler üretir,
- virmanda source/target yönlerini tersler,
- supplier payment'ta finans hesabına `in` ve supplier cari `credit` üretir,
- original immutable kalır.

## Post-write verify

Virman:

- tam iki finans movement,
- aynı tutar,
- aynı currency,
- source out,
- target in.

Supplier payment:

- tek finans out,
- tek contact debit,
- tutarların temel para birimi karşılığı tutarlı,
- duplicate idempotency etkisi yok.

Uyuşmazlık rollback.
