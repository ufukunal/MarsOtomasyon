# 07 — Durum Makineleri V4.1

State adları DB/API'de kontrollü enum/value-object veya explicit value set ile yönetilir. UI Türkçe karşılığını gösterir. Her transition authorization + invariant kontrolünden geçer. Generic state-machine package zorunlu değildir.

## 1. Genel kural
Lifecycle ile miktar/progress ayrı kavramlardır. Client state'i doğrudan set etmez; command/use-case transition ister. UI normal kullanıcıya queue/retry/provider internal state göstermez.

State history gerektiğinde tarih/kullanıcı/açıklama ile korunur. Reversal/correction finalized state'i silent mutate etmez; linked ters effect üretir.

## 2. Teklif
`Taslak → Gönderildi → Onaylandı | Reddedildi | Süresi Doldu`.

Revizyon gerektiğinde finalized revizyon mutate edilmez; yeni revision oluşturulur. Onaylı teklif siparişe dönüştürülebilir.

## 3. Satış Siparişi
`Taslak → Onaylandı → Kısmi İşlemde → Tamamlandı | Beklemede | İptal | Sorun`.

Sevk/faturalama progress net/reversal-safe satır miktarlarından türetilir. Remaining quantity çözülmeden tamamlandı olmaz. Marketplace reserve başarısızsa `Sorun/Stok Eksik` operasyon nedeni taşıyabilir.

## 4. İrsaliye / Sevkiyat
`Taslak → Kesinleşti → Sevk Edildi/Teslim Sürecinde → Teslim Edildi | İptal/İade`.

Finalized satırlar readonly'dir. Default sales flow'da posting stock OUT authority'sidir. Düzeltme reversal/iptal/yeni belge ile yapılır.

## 5. Satış / Alış Faturası
`Taslak → Kesinleşti/Posted → Reversed/Düzeltildi`.

İrsaliyesiz direct sales invoice fiziksel çıkışı temsil ediyorsa posting sırasında stock OUT üretebilir. Fatura settlement state (`paid/partial`) cari authority değildir.

## 6. E-Belge
Internal invoice'dan bağımsız provider lifecycle:
`Gerekli Değil | Bekliyor → Gönderiliyor → Kabul | Red | Hata | Belirsiz`.

Marketplace state e-belge state'ini overwrite edemez.

## 7. Satınalma Siparişi
`Taslak → Onaylandı → Kısmi İşlemde → Tamamlandı | Beklemede | İptal`.

Mal kabul accepted progress ve faturalama progress ayrı izlenir.

## 8. Mal Kabul
`Taslak → Kontrol → Kesinleşti → Reversed/Düzeltildi`.

Satır quantity'leri `accepted | pending_quality | rejected` olarak bölünebilir. Pending quantity sonradan `accepted` veya `rejected` reclassification alır; yeni physical receipt yaratmaz.

## 9. Depo Transferi
Fiziksel depolar arası gerçek taşıma desteklenir:
`Taslak → Onaylandı → Çıkış Yapıldı → Yolda → Kısmi Alındı | Alındı → Tamamlandı | İptal`.

Kurallar:
- kaynak çıkışı sonrası quantity/value in-transit custody'dedir
- çıkış/kabul miktarları transfer totalini aşmaz
- kısmi kabul mümkündür
- açık fark reconcile edilmeden tamamlanmaz
- cari/fiyat/KDV lifecycle'a dahil değildir

Aynı lokasyon içi anlık transfer daha kısa transaction use-case'i kullanabilir.

## 10. Stok Sayımı
`Taslak → Sayımda → İnceleme/Kontrol → Onaylandı → Post Edildi | İptal`.

Posting adjustment effect'i yalnız bir kez üretir. Positive adjustment cost yoksa explicit valuation bekler.

## 11. Kasa Sayımı
`Taslak → Sayımda → Tamamlandı | İptal`.

Fark varsa açıklama zorunlu. Completed count tekrar post edilmez.

## 12. Tahsilat / Ödeme / Gider
`Taslak → Posted/Kesinleşti → Reversed/Düzeltildi`.

Payment type state değildir; ayrı dimension'dır: Nakit, Banka, POS, Sanal POS, Çek, Senet, Diğer.

## 13. POS / Sanal POS settlement
Operasyon state'i:
`Pending → Settled | Reversed | Chargeback`.

Gross collection cari effect'i posting anında oluşur. `Settled` banka treasury movement'ı üretir fakat cariyi ikinci kez etkilemez.

## 14. Banka ekstresi import satırı
Kullanıcı-visible durumlar:
- `Eşleşti`
- `Eşleştirme Bekliyor`
- `Daha Önce Aktarıldı`
- `Aktarıldı`

Parse/fingerprint/duplicate/checksum gibi teknik durumlar normal UI'a çıkmaz.

## 15. Banka mutabakatı
`Eşleşmedi → Önerildi → Eşleşti`.

Match kaldırılırsa history korunur; re-match ikinci treasury movement oluşturmaz.

## 16. İade / RMA
Core lifecycle:
`Talep/Taslak → Yetkilendirildi/Kabul → Gönderim Bekliyor/Teslim Alındı → İnceleme → Karar → Tamamlandı | İptal`.

Kaynak türüne göre bazı ara adımlar atlanabilir. Provider-specific marketplace status M17/M18 adapterları tarafından bu normalizasyona map edilir. Stok/finans effect linked fakat kendi authority'lerinde yürür.

## 17. Basit Üretim Emri
V16.3 basit üretim için gereksiz MRP state'i eklenmez:
`Taslak → Hazır → Üretimde → Tamamlandı | Beklemede | İptal`.

Progress:
- malzeme çıkışı
- mamul girişi
- fire/eksik

Routing/work-center/operation-run lifecycle yoktur.

## 18. Fason
`Taslak → Malzeme Gönderildi → İşlemde → Kısmi Teslim → Uzlaştırma → Tamamlandı | İptal`.

Gönderilen malzeme subcontract custody'dedir; gelen/fire-eksik/kalan ayrı progress'tir.

## 19. İthalat / Konteyner
`Planlama → Sipariş/Yükleme → Yolda → Varış/Gümrük → Kabul → Maliyet Dağıtımı → Tamamlandı`.

`Kabul` kendi başına stock movement anlamına gelmez; linked GoodsReceipt/ImportReceipt handoff gerekir. Yükleme simülatörü state authority değildir.

## 20. Alınan Çek / Senet
`Portföyde → Bankada Tahsilde | Ciro Edildi → Tahsil Edildi`.

İstisna durumlar:
`Karşılıksız | İade Edildi | İptal`.

`Ciro Edildi` transition'ı supplier payable effect reference ve holder/physical-location değişimini taşır. Karşılıksız/iptal lifecycle'a göre supplier ve customer reversal chain'i çalıştırabilir.

## 21. Verilen Çek / Senet
`Hazırlandı → Teslim Edildi → Ödendi`.

İstisna durumlar:
`Karşılıksız/Ödenmedi | İade Alındı | İptal`.

Instrument movement history append-like korunur.

## 22. Marketplace settlement/payout
Provider evidence lifecycle provider'a göre normalize edilir:
`Bekliyor → Alındı/İçe Aktarıldı → Uzlaştırıldı | Sorun`.

Settlement/payout state finans authority değildir; AccountTransaction/TreasuryMovement source-effect references authority'dir. Aynı provider settlement external identity tekrar işlendiğinde ikinci effect oluşmaz.

## 23. E-Ticaret Siparişi
Kanal operasyon durumu:
`Yeni → Hazırlanıyor → Gönderildi → Tamamlandı | İptal | Sorun`.

Mars SalesOrder lifecycle ayrı authority'dir; provider callback keyfi overwrite yapmaz.

## 24. Integration connection
Normal kullanıcıya yalnız:
`Aktif | Pasif | Bağlantı Bekliyor | Sorun`
ve son test zamanı gösterilir.

Internal queue/retry/ambiguous state diagnostics tarafındadır.

## 25. Company
`active ↔ suspended → archived`.

Suspended normal business mutation/outbound processing'i bloke eder. Archived read/audit terminaldir.
