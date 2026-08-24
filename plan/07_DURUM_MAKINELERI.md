# 07 — Durum Makineleri V4

State adları DB/API'de kontrollü enum/value-object veya explicit value set ile yönetilir. UI Türkçe karşılığını gösterir. Her transition authorization + invariant kontrolünden geçer. Generic state-machine package zorunlu değildir.

## 1. Genel kural
Lifecycle ile miktar/progress ayrı kavramlardır. Client state'i doğrudan set etmez; command/use-case transition ister. UI normal kullanıcıya queue/retry/provider internal state göstermez.

State history gerektiğinde tarih/kullanıcı/açıklama ile korunur.

## 2. Teklif
`Taslak → Gönderildi → Onaylandı | Reddedildi | Süresi Doldu`.

Revizyon gerektiğinde finalized revizyon mutate edilmez; yeni revision oluşturulur. Onaylı teklif siparişe dönüştürülebilir.

## 3. Satış Siparişi
`Taslak → Onaylandı → Kısmi İşlemde → Tamamlandı | Beklemede | İptal`.

Sevk/faturalama progress satır miktarlarından türetilir. Remaining quantity çözülmeden tamamlandı olmaz.

## 4. İrsaliye / Sevkiyat
`Taslak → Kesinleşti → Sevk Edildi/Teslim Sürecinde → Teslim Edildi | İptal/İade`.

Finalized satırlar readonly'dir. Düzeltme reversal/iptal/yeni belge ile yapılır.

## 5. Satış / Alış Faturası
`Taslak → Kesinleşti/Posted → Reversed/Düzeltildi`.

Fatura settlement state (`paid/partial`) cari authority değildir.

## 6. E-Belge
Internal invoice'dan bağımsız provider lifecycle:
`Gerekli Değil | Bekliyor → Gönderiliyor → Kabul | Red | Hata | Belirsiz`.

Marketplace state e-belge state'ini overwrite edemez.

## 7. Satınalma Siparişi
`Taslak → Onaylandı → Kısmi İşlemde → Tamamlandı | Beklemede | İptal`.

Mal kabul ve faturalama progress ayrı izlenir.

## 8. Mal Kabul
`Taslak → Kontrol → Kesinleşti → Reversed/Düzeltildi`.

Satır kontrol kararı:
`Uygun | Kontrol Bekliyor | Uygun Değil`.

## 9. Depo Transferi
Fiziksel depolar arası gerçek taşıma desteklenir:
`Taslak → Onaylandı → Çıkış Yapıldı → Yolda → Kısmi Alındı | Alındı → Tamamlandı | İptal`.

Kurallar:
- çıkış/kabul miktarları transfer totalini aşmaz
- kısmi kabul mümkündür
- açık fark reconcile edilmeden tamamlanmaz
- cari/fiyat/KDV lifecycle'a dahil değildir

Aynı lokasyon içi anlık transfer daha kısa transaction use-case'i kullanabilir; kullanıcı-visible modelin doğruluğunu bozmaz.

## 10. Stok Sayımı
`Taslak → Sayımda → İnceleme/Kontrol → Onaylandı → Post Edildi | İptal`.

Posting adjustment effect'i yalnız bir kez üretir.

## 11. Kasa Sayımı
`Taslak → Sayımda → Tamamlandı | İptal`.

Fark varsa açıklama zorunlu. Completed count tekrar post edilmez.

## 12. Tahsilat / Ödeme / Gider
`Taslak → Posted/Kesinleşti → Reversed/Düzeltildi`.

Payment type state değildir; ayrı dimension'dır: Nakit, Banka, POS, Sanal POS, Çek, Senet, Diğer.

## 13. Banka ekstresi import satırı
Kullanıcı-visible durumlar:
- `Eşleşti`
- `Eşleştirme Bekliyor`
- `Daha Önce Aktarıldı`
- `Aktarıldı`

Parse/fingerprint/duplicate/checksum gibi teknik durumlar normal UI'a çıkmaz.

## 14. Banka mutabakatı
`Eşleşmedi → Önerildi → Eşleşti`.

Match kaldırılırsa history korunur; re-match ikinci movement oluşturmaz.

## 15. İade / RMA
`Talep/Taslak → Yetkilendirildi/Kabul → Gönderim Bekliyor/Teslim Alındı → İnceleme → Karar → Tamamlandı | İptal`.

Kaynak türüne göre bazı ara adımlar atlanabilir. Stok/finans effect linked fakat kendi authority'lerinde yürür.

## 16. Basit Üretim Emri
V16.3 basit üretim için gereksiz MRP state'i eklenmez:
`Taslak → Hazır → Üretimde → Tamamlandı | Beklemede | İptal`.

Progress:
- malzeme çıkışı
- mamul girişi
- fire/eksik

Routing/work-center/operation-run lifecycle yoktur.

## 17. Fason
`Taslak → Malzeme Gönderildi → İşlemde → Kısmi Teslim → Uzlaştırma → Tamamlandı | İptal`.

Gönderilen/gelen/fire-eksik/kalan ayrı progress'tir.

## 18. İthalat / Konteyner
`Planlama → Sipariş/Yükleme → Yolda → Varış/Gümrük → Kabul → Maliyet Dağıtımı → Tamamlandı`.

Yükleme simülatörü state authority değildir; planlama aracıdır.

## 19. Alınan Çek / Senet
`Portföyde → Bankada Tahsilde | Ciro Edildi → Tahsil Edildi`.

İstisna durumlar:
`Karşılıksız | İade Edildi | İptal`.

## 20. Verilen Çek / Senet
`Hazırlandı → Teslim Edildi → Ödendi`.

İstisna durumlar:
`Karşılıksız/Ödenmedi | İade Alındı | İptal`.

Instrument movement history append-like korunur.

## 21. E-Ticaret Siparişi
Kanal operasyon durumu:
`Yeni → Hazırlanıyor → Gönderildi → Tamamlandı | İptal | Sorun`.

Mars SalesOrder lifecycle ayrı authority'dir; provider callback keyfi overwrite yapmaz.

## 22. Integration connection
Normal kullanıcıya yalnız:
`Aktif | Pasif | Bağlantı Bekliyor | Sorun`
ve son test zamanı gösterilir.

Internal queue/retry/ambiguous state diagnostics tarafındadır.

## 23. Company
`active ↔ suspended → archived`.

Suspended normal business mutation/outbound processing'i bloke eder. Archived read/audit terminaldir.
