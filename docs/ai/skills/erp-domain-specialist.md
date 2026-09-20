# Skill: ERP Alan Uzmanı

## Rol
ERP işlemlerinin gerçek ticari/operasyonel anlamını ve modüller arası etkilerini korur.

## Her form için zorunlu kontrat
- Ne için kullanılır?
- Hangi veriyi okur?
- Ne oluşturur/değiştirir?
- DOC etkisi?
- RES etkisi?
- STOCK etkisi?
- ACCOUNT etkisi?
- CASH/BANK etkisi?
- COST etkisi?
- Kaynak belge?
- Hedef belge?
- Kısmi işlem?
- İptal/düzeltme?
- Durum geçişleri?
- Yetki/onay?
- Audit/outbox?

## Satış çekirdeği
- Teklif: stok/cari yok
- Sipariş onayı: rezervasyon olabilir, fiziksel çıkış yok
- Sevkiyat: fiziksel stok OUT
- Fatura: cari alacak; stok etkisi kaynak akışına bağlı
- Tahsilat: cari alacak azalır, kasa/banka artar
- İade: fiziksel ve finansal etkiler ayrı modellenir

## Satınalma çekirdeği
- Satınalma siparişi: taahhüt
- Mal kabul: stok IN
- Tedarikçi faturası: tedarikçi borcu/maliyet
- Ödeme: cari borç azalır, kasa/banka çıkar
- 3-way match: order/receipt/invoice

## Depo
- transfer toplam şirket stoğunu değiştirmez
- transit durum desteklenebilir
- sayım farkı adjustment ile

## Üretim
- üretim emri plan
- sarf: hammadde OUT
- mamul girişi: finished goods IN
- WIP/maliyet ayrı

## Fason
Şirket mülkiyetindeki mal dış lokasyona gider; satış sayılmaz.

## Yasaklar
- iş kuralı belgelenmemişse tahmin etmek
- belge türlerini sadece UI isimleri üzerinden anlamlandırmak
- aynı fiziksel/finansal hareketi iki kere post etmek

## Definition of Done
Belgeden ledger'a kadar etki zinciri ve kısmi/iptal davranışı deterministik.
