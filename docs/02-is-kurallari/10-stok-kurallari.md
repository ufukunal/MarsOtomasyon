# Stok kuralları

## Tek kaynak

Stok değişiyorsa bir `stock_movements` satırı vardır. `stock_balances`
yalnızca `RecordStockMovement` action'ı tarafından güncellenir.

**Hiçbir yerde `stock_balances` doğrudan yazılmaz.**

## Negatif stok

Ürün bazındadır (`products.allow_negative_stock`):

- İzinliyse: uyarı verilir, işlem yapılır, satır işaretlenir
- İzinsizse: işlem **engellenir**, hata mesajı gösterilir

Mal yolda olduğunda satışın durmaması için varsayılan izinlidir, ama
kritik ürünlerde kapatılabilir.

## Rezervasyon

Sipariş **satırı** bazında seçilir ("stoktan rezerve edilsin mi").

- Rezerve stoktan **düşmez**, `stock_balances.reserved` alanını artırır
- Kullanılabilir miktarı azaltır
- Sevkiyat yapılınca rezerv çözülür, stok düşer
- Sipariş iptal edilirse rezerv çözülür
- Rezerve edilen miktar fiziksel stoktan fazla olamaz (uyarı)

## Karantina

Satış iadesinde mal `quarantine` alanına girer. Kontrol sonrası:

- **Satılabilir** → karantinadan çıkar, normal stoğa geçer
- **Hurda** → karantinadan çıkar, `scrap` hareketiyle stoktan düşer

Karantinadaki mal satılamaz, rezerve edilemez, transfer edilemez.

## Konsinye ve numune

Gönderilen mal stoktan **düşmez**, `consignment_reserved` alanında durur.
Satıldığı bildirilince normal çıkış hareketi yazılır ve konsinye rezervi
çözülür. Geri gelirse rezerv çözülür, stok değişmez.

## Sayım

- Sayım belgesi açılır, lokasyon ve ürün aralığı seçilir
- Sistem miktarı dondurulur, sayılan miktar girilir
- Fark listesi çıkar
- **Fark elle onaylanır** (K: sayım farkları elle doğrulanacak)
- Onay sonrası fark kadar düzeltme hareketi yazılır (`reason = count`)
- Sayım sırasında o lokasyonda hareket yapılmaması önerilir (uyarı verilir,
  engellenmez)

## Transfer

- Çıkış ve giriş **aynı işlemde** yazılır
- Maliyet değişmez, aynı birim maliyet iki harekete de yazılır
- Kısmi teslim mümkündür: gönderilen ve teslim alınan miktar ayrı izlenir
- Yolda olan mal çıkış lokasyonundan düşmüş, giriş lokasyonuna girmemiştir;
  "yolda" durumu transfer belgesinden okunur

## Dönem kilidi

Her stok hareketinde `EnsurePeriodOpen` çağrılır. Kapalı aya hareket
yazılamaz.
