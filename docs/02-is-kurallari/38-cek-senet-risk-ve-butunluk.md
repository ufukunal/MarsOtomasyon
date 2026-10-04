# Çek/Senet risk ve bütünlük

## Ticari risk

K-078 gereği portföydeki henüz tahsil edilmemiş kıymetler satış siparişi risk projeksiyonunda ayrıca gösterilir.

Faz 5 kanonik risk seti, **alınan** kıymetlerden şirkette ekonomik olarak henüz sonuçlanmamış olanlardır:

- `portfolio`
- `sent_to_collection`

`received` yalnız ilk kayıt anındaki geçici durumdur; portfolio'ya alınmadan operasyon tamamlanmış sayılmaz ve açık risk listesinde ayrıca gösterilebilir, ancak çift sayılmaz.

Şunlar portföy riskine dahil edilmez:

- `endorsed` — kıymet artık karşı tarafa verilmiştir,
- `collected`,
- `bounced_or_returned`,
- issued kıymetler.

Risk toplamı cari bakiyeye yazılmaz; K-078 projeksiyonunda ayrı bileşendir.

## Risk tutarı

Risk şirket temel para biriminde hesaplanır. Faz 5 yeni FX değerleme motoru kurmaz.

Aynı security yalnız bir kez sayılır.

## Cari etki matrisi

| Olay | Original cari | Karşı cari | Finans hesabı |
|---|---|---|---|
| received ilk teslim | credit | yok | yok |
| endorse | tekrar yok | debit | yok |
| sent_to_collection | yok | yok | yok |
| collected | yok | yok | bank in |
| received bounce/return | önceki cari etkinin inverse'i | gerekiyorsa etkin ciro etkisinin inverse'i | gerekiyorsa finans ters etkisi |
| issued ilk teslim | debit | yok | yok |
| paid | yok | yok | bank out |
| issued return/cancel | önceki debit'in inverse credit'i | yok | gerekiyorsa finans ters etkisi |

Her ters hareket açıkça önceki harekete bağlanır.

## Cari yaşlandırma

Çek/senet cari hareketleri `contact_transactions` içinde diğer hareketlerle aynı bakiye formülüne girer.

K-063 sanal FIFO yaşlandırma:

- exact inverse çiftlerini normalize eder,
- yeni settlement tablosu oluşturmaz.

Çek/senet event tablosu cari bakiyenin ikinci kaynağı değildir.

## Integrity

`integrity:securities`:

- her security ilk event/direction uyumu,
- event transition sırası,
- current_status = son etkin event sonucu,
- received ilk event için tek credit,
- issued ilk event için tek debit,
- collect/pay'de ikinci cari hareket olmaması,
- endorse'da original contact'ın ikinci kez etkilenmemesi,
- endorse counterparty debit tutarı,
- return/bounce inverse movement bağlantıları,
- collected bank in / paid bank out,
- risk setinde yalnız uygun received status'ların bulunması

kontrollerini yapar.

## Concurrency

- security row `lockForUpdate`,
- optimistic `version`,
- aynı transition idempotency key,
- concurrent endorse/collect veya pay/return yarışında yalnız bir geçerli state transition commit eder.

## Düzeltme

Integrity farkı raporlanır. Otomatik düzeltme yapılmaz.
