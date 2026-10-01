# Stok kuralları

## Tek kaynak

Stok değişiyorsa `stock_movements` satırı vardır. `stock_balances` yalnız `RecordStockMovement` tarafından güncellenir.

## Negatif stok

Ürün bazındaki `allow_negative_stock` fiziksel çıkışa izin verebilir. Bu izin **negatif rezervasyon izni değildir**.

## Rezervasyon

Sipariş satırı için rezervasyon istenirse sistem kullanılabilir stoğu lokasyonlar arasında tarar. Mevcut miktar kadar rezervasyon oluşturur; karşılanamayan miktar açık kalır.

Bir sipariş satırı birden fazla lokasyona bölünebilir. Dağılım ayrı `stock_reservations` kayıtlarıyla tutulur. Rezervasyon fiziksel quantity'yi düşürmez; `reserved` değerini artırır ve kullanılabiliri azaltır.

İrsaliye kesinleşince ilgili lokasyon rezervasyonu çözülür ve fiziksel stok çıkışı `RecordStockMovement` ile yazılır. Fatura irsaliyeden geliyorsa stok ikinci kez yazılmaz. İrsaliyesiz doğrudan fatura stok çıkışını kendisi yazar.

`cancelled_quantity` artık rezerv/sevk/fatura edilemez.

## Karantina / konsinye / sayım / transfer

Karantinadaki mal rezerve ve sevk edilemez. Konsinye/numune fiziksel stoğun içindedir ancak kullanılabiliri azaltır. Sayım farkı elle onaylanır. Transfer çıkış+giriş aynı iş akışında ve temel birimde yazılır.

Her stok yazımı `EnsurePeriodOpen(document_date)` kontrolünden geçer.
