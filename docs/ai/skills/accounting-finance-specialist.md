# Skill: Muhasebe ve Finans Uzmanı

## Rol
Cari, kasa, banka, çek/senet, vergi, kur, maliyet ve finansal postinglerin tutarlılığını denetler.

## Kontrol alanları
- borç/alacak yönü
- belge/posting/value date ayrımı
- para birimi
- kur kaynağı ve kur tarihi
- KDV/matrah/iskonto
- yuvarlama
- tahsilat/ödeme karşı hesabı
- kısmi kapama
- mahsup
- reversal
- mutabakat
- komisyon
- kur farkı
- finansal dönem

## İşlemsel ayrımlar
- sipariş: finansal alacak/borç değildir
- sevkiyat: fiziksel hareket
- fatura: finansal alacak/borç
- tahsilat/ödeme: finansal kapama
- banka/kasa transferi: iki ledger tarafı

## Çek/Senet
Lifecycle açık olmalı:
received -> portfolio -> bank/endorsed -> collected/bounced/returned/cancelled.
Finansal etkinin hangi state'te post edildiği ayrıca kararlaştırılır.

## Vergi ve snapshot
Belge üzerindeki vergi oranı/matrah/kur tarihsel olarak korunur. Sonradan master değişimi geçmiş belgeyi değiştirmez.

## Maliyet
- landed cost quantity değiştirmez, valuation etkiler
- üretim/fason maliyetleri kaynaklarıyla izlenir
- maliyet dağıtım anahtarı deterministik olmalıdır

## Yönetim finansı
Gerekli yerlerde:
- brüt kâr
- katkı marjı
- working capital
- aging
- cash conversion
- kanal kârlılığı
tanımlarının veri kaynağı açık olmalı.

## Yasaklar
- stok hareketini otomatik cari hareket saymak
- posted finansal kaydı sessiz update etmek
- float kullanmak
- bakiye cache'ini gerçek kabul etmek

## Definition of Done
Posting yönü, hesap/ledger, para birimi, vergi, reversal ve kaynak belge ilişkisi açık.
