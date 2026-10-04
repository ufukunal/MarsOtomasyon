# G-503 — Tedarikçi ödeme

## Amaç

K-093/K-062'ye göre genel tedarikçi ödeme formunu ve alış faturası ödeme kısayolunu, zorunlu settlement oluşturmadan uygulamak.

## Önkoşul

G-501, Faz 4 purchase_invoice, contact_transactions ve PostDocument altyapısı.

## Dokunulacak dosyalar

- SupplierPayment form/detail bileşenleri
- alış faturası `Ödeme Yap` action/kısayolu
- `PostSupplierPayment`
- payment_source relation
- supplier payment feature testleri

## Şema / Kod

supplier_payment:

- contact_id = supplier
- header amount
- source purchase invoice optional
- cash veya bank account zorunlu

Posting:

- contact_transactions.debit
- selected finance account out
- optional payment_source relation

## Kurallar

- Kaynak fatura zorunlu değil.
- Kısmi ödeme serbest.
- payment_source yalnız bilgi amaçlı.
- Invoice settlement/paid/remaining tablosu veya alanı oluşturulmaz.
- Faz 5 yeni FX dönüşüm motoru kurmaz.
- K-013/iş kuralı 35 gereği supplier_payment ledger etkisi şirket temel para birimindedir; seçilen cash/bank account currency ödeme ile uyumlu olmalıdır. Uyumlu olmayan para biriminde sessiz conversion/kur farkı yapılmaz.
- Wrapper posting etkisini ikinci kez yazmaz.

## Kabul ölçütü

- Genel formdan faturasız supplier payment yapılabiliyor.
- Purchase invoice kısayolu supplier/source relation'ı hazır getiriyor.
- Kullanıcı tutarı değiştirebiliyor.
- Contact debit supplier borcunu azaltıyor.
- Cash payment cash out, bank payment bank out üretiyor.
- Uyumlu olmayan finans hesabı currency'si reddediliyor; FX conversion veya kur farkı hareketi üretilmiyor.
- Source invoice seçimi bakiye hesabını farklılaştırmıyor.
- Idempotency duplicate payment üretmiyor.
- Reverse finance in + contact credit üretiyor.

## İstem

> supplier_payment'i K-093'e göre uygula. Fatura ilişkisini settlement gerçek kaynağına dönüştürme; cari gerçekliği contact_transactions olarak kalsın.
