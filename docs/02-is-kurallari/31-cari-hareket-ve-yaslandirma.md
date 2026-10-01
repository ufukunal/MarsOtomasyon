# Cari hareket, bakiye ve yaşlandırma

## Tek kaynak

Cari bakiye `contact_transactions` hareket toplamıdır. `contacts` üzerinde balance kolonu yoktur ve bakiye cache edilmez.

```
bakiye = debit toplamı - credit toplamı
```

Fatura settlement tablosu bakiye kaynağı değildir.

## Faz 3 hareketleri

### Satış faturası

Posted satış faturası:

```
direction = debit
transaction_type = sales_invoice
amount = document.grand_total
transaction_date = document.document_date
due_date = document.due_date
document_id = invoice.id
```

### Tahsilat

Posted tahsilat:

```
direction = credit
transaction_type = collection
amount = document.grand_total
transaction_date = document.document_date
document_id = collection.id
```

Tahsilat kasa/banka movement ile aynı transaction içinde yazılır.

### Manuel Cari Borç/Alacak Fişi

K-081 gereği vardır. Gerekçe ve period audit zorunludur. Bu belge de posted olduğunda tek contact transaction üretir.

## Fatura içinden tahsilat

Fatura detayındaki "Tahsilat" eylemi tahsilat formunu cari ve kaynak belge bilgisiyle açabilir.

Bu:

- tahsilatı o faturaya kilitlemez,
- kalıcı settlement dağıtımı üretmez,
- yalnız `document_relations.collection_source` gibi bilgi amaçlı ilişki kurabilir.

## FIFO yaşlandırma

Amaç muhasebe settlement'ı değil **bilgilendirme**dir.

1. Raporun "as of" tarihinden sonraki hareketler hesaba katılmaz.
2. Debit/borç doğuran hareketler due_date + transaction_date + id sırasına dizilir.
3. Credit hareket toplamı en eski borçtan başlayarak uygulanmış kabul edilir.
4. Dağıtım runtime rapor hesabıdır; DB'ye fatura tahsilat eşleştirmesi yazılmaz.
5. Kalan her borç satırı vade gecikmesine göre dilime düşer.
6. Credit toplamı debit toplamını aşarsa açık alacak 0'dır; fazla credit ayrı "cari alacak/avans" bilgisi olarak gösterilir.

Dilimler:

- Vadesi gelmemiş
- 1–30
- 31–60
- 61–90
- 91–120
- 120+

## Satır renkleri

- **Yeşil:** borç tamamen sanal FIFO ile kapanmış.
- **Sarı:** bir kısmı kapanmış, bakiye kalmış.
- **Kırmızı:** bu borca henüz hiç mahsup düşmemiş.

Renk yalnız sunumdur; transaction verisini değiştirmez.

## Çek / senet etkisi

Tam yaşam döngüsü Faz 5'tedir. K-082 kuralı değişmez:

- kıymet teslim alındığında cari credit etkisi oluşur,
- tahsil olduğunda cari ikinci kez etkilenmez,
- karşılıksız/geri dönüş ters cari hareket üretir,
- ciro karşı taraf carisini ayrıca etkileyebilir.

## Risk

Resmî cari risk bakiyesi = cari bakiye.

Sipariş ekranındaki ticari projeksiyon:

```
cari bakiye
+ bu sipariş tutarı
+ portföyde henüz tahsil edilmemiş çek/senet riski
```

Diğer açık siparişler resmî bakiyeye katılmaz. Limit aşımı uyarıdır, blok değildir.

## integrity:contacts

- contact_transactions toplamı raporlanan bakiye ile eşleşir,
- document_id bağlı satış faturası/tahsilat için tek hareket bulunur,
- reversal zinciri çift uygulanmaz,
- yaşlandırma kalan debit toplamı `max(cari bakiye, 0)` ile tutarlı olmalıdır; negatif bakiye excess credit olarak ayrıca gösterilir.

Otomatik düzeltme yapılmaz.
