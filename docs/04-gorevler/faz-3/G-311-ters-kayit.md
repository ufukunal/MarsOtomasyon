# G-311 — Ters kayıt / Reverse

## Amaç

Kesinleşmiş satış belgelerini yerinde değiştirmeden, yeni ters belge ve ters hareketlerle düzeltmek.

## Önkoşul

G-303, G-306, G-307, G-309.

## Dokunulacak dosyalar

- `app/Actions/Documents/ReverseDocument.php`
- `app/Actions/Documents/VerifyReversal.php`
- `app/Exceptions/Documents/AlreadyReversedException.php`
- `tests/Feature/Documents/ReverseDocumentTest.php`

## Temel kural

K-016/K-079:

- posted belge silinmez,
- satır/tutar/değerleri yerinde değiştirilmez,
- yeni reversal document oluşturulur,
- `document_relations.reversal_of` yeni belgeyi orijinale bağlar,
- gerekçe zorunludur,
- audit zorunludur.

Orijinal belgenin iş alanlarını değiştirme. UI, aktif `reversal_of` ilişkisi varsa kaydı "Terslendi" olarak gösterebilir.

Bir belge ikinci kez terslenemez.

## Reversal tarihi

Ters kayıt yeni `document_date` alır ve **o tarih açık period ayı olmalıdır**. Orijinal belge tarihini geri yazıp kapalı aya hareket ekleme.

## İrsaliye reversal

Orijinal dispatch her line için stock out üretti.

Reversal:

- aynı product/location/base_quantity için stock **in** ters hareket,
- original dispatch fulfillment etkisi partial hesapta artık etkin sayılmaz,
- cari hareket yok.

**Rezervasyon otomatik yeniden oluşturulmaz.** Mevcut kaynaklar reverse sonrası otomatik reserve kuralı tanımlamıyor; kullanıcı siparişte gerekirse yeniden rezervasyon yapar.

## Satış faturası reversal

### İrsaliyeden fatura

Orijinal invoice stok yazmadı:

- reversal contact transaction **credit**,
- stok hareketi yok.

### Direct invoice

Orijinal invoice stock out + debit:

- reversal stock **in**,
- reversal contact **credit**.

Reversal sonrası source order/dispatch partial miktar hesaplarında terslenmiş invoice fulfillment olarak sayılmaz.

## Tahsilat reversal

Orijinal collection:

- contact credit,
- cash/bank in.

Reversal:

- contact debit,
- aynı hesaba cash/bank out,
- orijinal hareket silinmez.

## Numara

Reversal yeni bir `documents` kaydıdır ve kendi numarasını transaction içinde alır. Orijinal number korunur; relation kaynak bağlantısını gösterir.

## Doğrulama

`VerifyReversal`:

- reversal total = original total,
- cari yönü tam ters,
- stok etkisi olan original için aynı base_quantity ters yön,
- cash/bank collection için aynı hesap ve tutar ters yön,
- ikinci reversal ilişkisi yok.

## Partial query etkisi

G-30 kısmi işlem sorguları yalnız **etkin** posted child belgeleri sayar. `reversal_of` ilişkisiyle terslenmiş dispatch/invoice child miktarı fulfillment toplamına dahil edilmez.

## Kabul ölçütü

- Posted belge alanları değişmiyor.
- Dispatch reverse stok geri getiriyor, cari yazmıyor.
- Dispatch reverse otomatik reservation oluşturmuyor.
- Dispatch-source invoice reverse yalnız cari credit.
- Direct invoice reverse stock in + cari credit.
- Collection reverse cari debit + aynı hesapta out movement.
- Aynı original ikinci kez reverse edilemiyor.
- Kapalı reversal date reddediliyor.
- Partial kalan miktar reverse sonrası doğru yeniden açılıyor.
- Audit gerekçeyi ve actor'u içeriyor.

## İstem

> ReverseDocument'ı yeni reversal document + ters hareket yaklaşımıyla uygula. Orijinal posted belgenin iş alanlarını update etme. Original etkisine göre stock/cari/cash-bank yönlerini tam ters yaz. Terslenmiş child belgeleri partial fulfillment toplamından çıkar.
