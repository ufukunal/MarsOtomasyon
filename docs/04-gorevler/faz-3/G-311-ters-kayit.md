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


## Şema / Kod

Yeni tablo yok. Reversal yeni `documents/document_lines` kaydı, `document_relations.reversal_of` ve orijinal etkinin ters `stock_movements/contact_transactions/cash_movements/bank_movements` kayıtlarını kullanır.

## Temel kural

K-016/K-079:

- posted belge silinmez,
- satır/tutar/değerleri yerinde değiştirilmez,
- yeni reversal document oluşturulur,
- `document_relations.reversal_of` yeni belgeyi orijinale bağlar,
- gerekçe zorunludur,
- audit zorunludur.

Orijinal belgenin iş alanlarını değiştirme. UI, aktif `reversal_of` ilişkisi varsa kaydı "Terslendi" olarak gösterebilir.

Bir belge ikinci kez terslenemez. `document_relations_one_reversal_per_target` partial unique index bu kuralı DB seviyesinde de korur.

## Reversal tarihi

Ters kayıt yeni `document_date` alır ve **o tarih açık period ayı olmalıdır**. Orijinal belge tarihini geri yazıp kapalı aya hareket ekleme.

## İrsaliye reversal

Orijinal dispatch her line için stock out üretti.

Aktif posted invoice child'ı bulunan dispatch **önce terslenemez**. Önce bağlı aktif faturalar reverse edilmelidir; aksi halde stok geri gelirken finansal belge aktif kalır ve fulfillment zinciri bozulur.

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

## Manuel Cari Borç / Alacak Fişi reversal

Orijinal `contact_debit_credit` hareketi:

- debit ise reversal credit,
- credit ise reversal debit,
- reversal contact transaction `reversal_of_id = original_contact_transaction.id` taşır,
- stok veya kasa/banka hareketi üretmez.

## Tahsilat reversal

Orijinal collection:

- contact credit,
- cash/bank in.

Reversal:

- contact debit ve `reversal_of_id = original_contact_transaction.id`,
- aynı hesaba cash/bank out,
- orijinal hareket silinmez.

## Reversal belge tipi ve numara

Reversal için yeni bir `reversal` document_type veya yeni number series **uydurulmaz**.

- reversal document, orijinal belgenin `document_type` değerini korur (`dispatch`, `sales_invoice`, `collection`, `contact_debit_credit`),
- aynı document type'ın mevcut period number series'inden **yeni** numara alır,
- `status = posted` olur,
- bunun normal belge değil reversal olduğu `document_relations.reversal_of` ilişkisiyle belirlenir,
- orijinal number ve orijinal belge değişmez.

Bu nedenle effect resolver reversal document'ı normal `PostDocument` akışına tekrar sokmaz; ters etkilerin sahibi yalnız `ReverseDocument`dır.

## Doğrulama

`VerifyReversal`:

- reversal total = original total,
- cari yönü tam ters ve contact reversal kaydı `reversal_of_id` ile orijinal harekete bağlı,
- stok etkisi olan original için aynı base_quantity ters yön,
- cash/bank collection için aynı hesap ve tutar ters yön,
- ikinci reversal ilişkisi yok.

## Partial query etkisi

G-30 kısmi işlem sorguları yalnız **etkin** posted child belgeleri sayar. `reversal_of` ilişkisiyle terslenmiş dispatch/invoice child miktarı fulfillment toplamına dahil edilmez.


## Kurallar

- Original posted belgenin iş alanları değişmez.
- Reversal yeni belge ve ters hareketler üretir.
- İkinci reversal yasaktır.
- Reverse sonrası otomatik rezervasyon yaratılmaz.

## Kabul ölçütü

- Posted belge alanları değişmiyor.
- Aktif invoice child'ı bulunan dispatch reverse reddediliyor.
- Dispatch reverse stok geri getiriyor, cari yazmıyor.
- Dispatch reverse otomatik reservation oluşturmuyor.
- Dispatch-source invoice reverse yalnız cari credit.
- Direct invoice reverse stock in + cari credit.
- Collection reverse cari debit + aynı hesapta out movement.
- Manuel cari debit fişi credit ile, credit fişi debit ile tersleniyor.
- Reversal yeni `reversal` tipi üretmiyor; orijinal document_type serisinden yeni numara alıyor.
- Aynı original ikinci kez reverse edilemiyor.
- Kapalı reversal date reddediliyor.
- Partial kalan miktar reverse sonrası doğru yeniden açılıyor.
- Audit gerekçeyi ve actor'u içeriyor.

## İstem

> ReverseDocument'ı yeni reversal document + ters hareket yaklaşımıyla uygula. Orijinal posted belgenin iş alanlarını update etme. Original etkisine göre stock/cari/cash-bank yönlerini tam ters yaz. Terslenmiş child belgeleri partial fulfillment toplamından çıkar.
