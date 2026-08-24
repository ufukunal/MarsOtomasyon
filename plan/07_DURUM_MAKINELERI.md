# 07 — Durum Makineleri

State adları DB/API'de kontrollü enum/value object ile yönetilir; UI Türkçe karşılığını gösterir. Her transition authorization + invariant kontrolünden geçer.

## Teklif
`Taslak → İç Onay/Gönderildi → Müşteri İncelemesi → Revizyon → Onaylandı | Reddedildi | Süresi Doldu`

## Satış Siparişi
`Taslak → Onaylandı → Kısmen Sevk/Faturalandı → Tamamlandı | Beklemede | İptal`

Progress state mümkün olduğunca satır miktarlarından türetilir; manuel state ile gerçek miktar çelişmez.

## İrsaliye/Sevkiyat
`Taslak → Kesinleşti → Sevk Edildi → Tamamlandı | İptal`

## Satış/Alış Faturası
`Taslak → Kesinleşti/Posted → Ters Kayıt/Reversed`

Posted belge edit edilmez.

## Satınalma Siparişi
`Taslak → Onaylandı → Kısmen Kabul → Kabul Tamam → Kapandı | İptal`

## Mal Kabul
`Taslak → Kontrol → Kesinleşti → Reversed`

Satır kalite sonucu: `Uygun | Kontrol Bekliyor | Uygun Değil`.

## Stok Transferi
`Taslak → Onaylandı → Post Edildi | İptal`

## Stok Sayımı
`Taslak → Sayımda → İnceleme → Onaylandı → Post Edildi | İptal`

## Üretim Emri
`Taslak → Planlandı → Serbest → Devam Ediyor → Tamamlandı → Kapandı` ve gerektiğinde `Beklemede/İptal`.

## Fason
`Taslak → Malzeme Gönderildi → İşlemde → Kısmi Teslim → Uzlaştırma → Tamamlandı/Kapandı`.

## Tahsilat/Ödeme/Gider
`Taslak → Posted → Reversed`.

## Alınan çek/senet
`Portföyde → Bankada Tahsilde | Ciro Edildi → Tahsil Edildi`.
İstisnalar: `Karşılıksız, İade Edildi, İptal`.

## Verilen çek/senet
`Hazırlandı → Teslim Edildi → Ödendi`.
İstisnalar: `Karşılıksız/Ödenmedi, İade Alındı, İptal`.

## RMA/İade
`Talep → Yetkilendirildi → Gönderim Bekliyor → Teslim Alındı → İnceleme → Karar → Tamamlandı | İptal`.

## İthalat
`Planlama → Sipariş/Yükleme → Yolda → Gümrük → Kabul → Maliyet Dağıtımı → Kapandı`.

## Transition kuralı
- client gönderdi diye state doğrudan set edilmez
- command/use-case transition ister
- izinli önceki state doğrulanır
- side-effect aynı transaction/outbox politikasıyla çalışır
- tarih/kullanıcı/açıklama state history'de tutulur
