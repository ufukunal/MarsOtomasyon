# 06 — İş Kuralları ve Invariantlar V4

Bu dosya MarsOtomasyon'un yanlış finansal/operasyonel sonuç üretmesini engelleyen owner business kurallarını tanımlar.

## 1. Company isolation
Tenant-scoped kayıt active company dışında yazılamaz. Job explicit company context taşır. Referanslanan tenant entity aynı Company olmalıdır.

## 2. Decimal / kur / rounding
Binary float yoktur. High-precision decimal kullanılır. Currency rounding explicit'tir; foreign/base amount gerektiğinde kur snapshot'ıyla saklanır.

## 3. Kesinleşmiş belge değişmezliği
Taslak düzenlenebilir. Kesinleşmiş belge/ledger hareketi silent UPDATE/DELETE edilmez. Düzeltme reversal/adjustment/return/iptal ile yapılır.

## 4. Belge toplamı
`gross_total = line_net_total + tax_total + charges_total - discount_total ± rounding_difference`.
Açıklanamayan fark blocker'dır.

## 5. Historical snapshot
Posted belge current cari/ürün/fiyat/vergi/kur master'ı değiştiğinde geçmiş değerlerini değiştirmez.

## 6. Cari bakiye
Authority `account_transactions`.
- debit `+`
- credit `-`
- balance = signed sum/rebuildable projection

### Satış
- satış faturası müşteri bakiyesini artırır
- tahsilat azaltır

### Alış
- alış faturası tedarikçi borcunu artırır
- ödeme azaltır

### Yasak
- invoice bazlı receivable/payable allocation yok
- OpenItem kapatma yok
- `paid/partially_paid` invoice settlement authority değildir

Vade bilgi/rapor alanıdır. Fazla tahsilat/ödeme signed bakiyeyi diğer yöne geçirebilir.

## 7. Cari görünümü
Signed balance kullanıcıya:
- `Alacaklı` yeşil
- `Borçlu` kırmızı
- zero `Bakiye Yok`
olarak gösterilir.

## 8. Sales quantity
Formüller:
`remaining_to_invoice = ordered - cancelled - invoiced`
`remaining_to_dispatch = ordered - cancelled - dispatched`

Kurallar:
- over-invoice default BLOCK
- over-dispatch default BLOCK
- kısmi işlem normaldir
- remaining < 0 olamaz
- remaining sıfıra geldiğinde ilgili progress tamamlanır

Reservation:
`reserved_qty <= remaining_fulfillable_qty`.
Sevk/cancel/line reduction kullanılmayan reservation'ı release eder.

## 9. Satış faturası posting
Tek DB transaction:
1. draft/finalize validation
2. invoice posted
3. related order invoiced/remaining update
4. stock policy gerektiriyorsa stock OUT
5. account transaction
6. outbox
7. commit

Bir adım başarısızsa tüm işlem rollback olur. Aynı command retry ikinci etki üretemez.

## 10. Physical stock exactly-once
Aynı fiziksel miktar iki farklı kaynaktan ikinci kez stock movement üretemez.

Örnek:
- dispatch authoritative stock OUT yaptıysa invoice aynı quantity'yi tekrar düşmez
- goods receipt stock IN yaptıysa supplier invoice tekrar giriş yapmaz

Satışta physical stock authority implementasyon kararında tek noktada seçilir; `19_ACIK_KARARLAR.md` A-02 kapanmadan iki kaynak birlikte etkinleştirilemez.

## 11. Purchasing quantity
`remaining_to_receive = ordered - cancelled - received_not_reversed`
`remaining_to_invoice = ordered - cancelled - invoiced_not_reversed`

- over-receipt default BLOCK
- over-invoice default BLOCK
- kısmi mal kabul ve kısmi fatura normaldir

## 12. Inventory
Authority `stock_movements`.
Reservation movement değildir.

Default negative stock policy merkezi ayardır; bypass yalnız explicit permission/policy ile olabilir.

Kullanılabilir stok:
`physical - reserved - quarantine/blocked`.

Kanal publish:
`available - channel_safety_stock`.

## 13. Depo transferi
Transfer cari/fiyat/KDV belgesi değildir.

Kaynak issue ve hedef receipt aynı transfer lineage'ında reconcile edilir. Transfer modeli operasyon ihtiyacına göre `çıkış → yolda → kısmi/tam kabul` aşamalarını destekler. Kaynak ve hedef hareketleri duplicate üretilemez.

## 14. Stok sayımı
Sistem miktarı, sayılan miktar ve fark tutulur. Finalization exactly-once adjustment üretir. Taslak stok etkisi üretmez.

## 15. Kasa sayımı
`difference = counted_total - system_balance`.

- denomination quantity toplamı counted total'i üretir
- fark varsa açıklama zorunlu
- taslak bakiye değiştirmez
- tamamlama yalnız bir adjustment effect üretir

## 16. Tahsilat / ödeme türü
Kullanıcı-visible tipler:
`cash | bank | pos | virtual_pos | check | promissory_note | other`.

Type-specific alanlar validation'a tabidir. Hidden type alanları posting authority değildir. Tanımlar kontrollü PaymentMethod/PaymentType config ile genişletilebilir.

## 17. Tahsilat / ödeme atomikliği
Tahsilat/ödeme tek transaction'da:
- source operation
- account transaction
- ilgili cash/bank/POS/instrument effect
üretir.

Yarım cari/treasury durumu oluşamaz.

## 18. POS
Cari effect = gross tahsilat. Komisyon ayrı expense/bank/POS effect'tir. Net bank settlement cariyi ikinci kez azaltamaz.

## 19. Virman
Same-currency virman kaynak çıkış + hedef giriş atomik çift harekettir ve toplam değer korunur. Cross-currency için explicit FX/rate/rounding policy gerekir.

## 20. Çek / Senet
### Müşteriden alınan
Teslim/posting anında müşteri bakiyesini azaltır ve portföy kaydı oluşturur. Bankada tahsil ikinci cari effect değildir. Karşılıksız/iptal reversal ile bakiyeyi yeniden açar.

### Tedarikçiye verilen
Teslim/posting anında tedarikçi borcunu azaltır. Bankadan ödeme ikinci cari effect değildir. Ödenmeme/iptal reversal ile borcu yeniden açar.

Instrument lifecycle ve physical location history silinmez. Aynı instrument iki kez settlement effect üretemez.

## 21. Mal kabul kontrolü
Core karar seti:
- `Uygun`
- `Kontrol Bekliyor`
- `Uygun Değil`

Gerekirse kontrol bekleyen/uygun olmayan miktar available stock dışında blocked/quarantine state'te tutulabilir. Generic QMS kurulmaz.

## 22. Pricing
Her üründe tek satış + tek alış fiyatı. B2B fiyatı:
`Product Sale Price - Cari Discount`.
Ayrı B2B discount source yoktur.

## 23. Production
Akış:
`Recipe → Production Order → Material Issue → Finished Good Receipt → Complete`.

- material issue stock OUT
- finished good receipt stock IN
- output/material quantity limitleri aşılmaz
- close öncesi material/output/fire/eksik reconcile edilir
- açıklamasız unresolved quantity ile tamamlama yok

## 24. Fason
Company-owned gönderilen malzeme custody'de izlenir. `gelen mamul + fire/eksik + kalan` reconcile edilmeden fason tamamlanmaz.

## 25. İthalat maliyeti
Container/genel cost allocation selected basis ile source total'e reconcile olmalıdır. Aynı cost item iki kez allocation üretemez. Ürün farklı konteynerlerden gelebilir; lineage korunur.

## 26. E-Ticaret inbound idempotency
External entity identity ile inbound message identity ayrıdır. Provider-account scoped duplicate event ikinci Sales Order/stock/financial effect üretemez.

## 27. E-Ticaret stock publish
`publish_qty = physical - reserved - quarantine/blocked - channel_safety_stock`.
Mars stock authority'dir; provider callback Mars stock truth'unu keyfi overwrite edemez.

## 28. B2B
B2B user/account bir Mars carisine önceden bağlıdır. Siparişte cari seçimi yoktur. B2B order cari bakiyesini etkilemez; invoice effect eder.

## 29. Bank statement import
Statement row stable identity/fingerprint ile duplicate guard kullanır. Aynı satır ikinci business movement üretmez.

Kullanıcı-visible durumlar:
- Eşleşti
- Eşleştirme Bekliyor
- Daha Önce Aktarıldı
- Aktarıldı

## 30. Bank reconciliation
Existing bank movement ile match ikinci bank movement yaratmaz. Unmatch/rematch history audit edilir.

## 31. Reports
Rapor/read model authority değildir. Scheduled report çalışma anında authorization/company context tekrar doğrulanır.

## 32. Search
Search result authority değildir. Transactional use-case source tablolara dayanır; search authorization/company scope'u bypass edemez.

## 33. Outbox / retry
Business change + durable Outbox aynı DB transaction'ında. External HTTP transaction dışında. Retry duplicate business effect üretemez. Ambiguous provider result blind resend edilmez; query/reconcile uygulanır.

## 34. Data correction
Production direct SQL/tinker business fix yasaktır. Correction command idempotent, audit'li ve source-lineage'lı olmalıdır.
