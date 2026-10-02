# Stok kuralları

## Tek kaynak

Fiziksel stok `quantity` değişiyorsa `stock_movements` satırı vardır ve `stock_balances.quantity` yalnız `RecordStockMovement` tarafından güncellenir. `reserved`, `quarantine` ve `consignment_reserved` fiziksel quantity değildir; yalnız kendi dedicated Action'ları tarafından, kendi kaynak kayıtlarıyla aynı transaction içinde güncellenir.

## Negatif stok

Ürün bazındaki `allow_negative_stock` fiziksel çıkışa izin verebilir. Bu izin **negatif rezervasyon izni değildir**.

## Rezervasyon

Sipariş satırı için rezervasyon istenirse sistem kullanılabilir stoğu lokasyonlar arasında tarar. Mevcut miktar kadar rezervasyon oluşturur; karşılanamayan miktar açık kalır.

Bir sipariş satırı birden fazla lokasyona bölünebilir. Dağılım ayrı `stock_reservations` kayıtlarıyla tutulur. Rezervasyon fiziksel quantity'yi düşürmez; `reserved` değerini artırır ve kullanılabiliri azaltır.

İrsaliye kesinleşince fiziksel stok çıkışı yalnız `RecordStockMovement` ile yazılır; `ConsumeReservation` yalnız ilgili lokasyon rezervini consumed yapıp `reserved` değerini azaltır. `ConsumeReservation` kendi stock movement'ını üretmez. Fatura irsaliyeden geliyorsa stok ikinci kez yazılmaz. İrsaliyesiz doğrudan fatura stok çıkışını kendisi yazar.

`cancelled_quantity` artık rezerv/sevk/fatura edilemez.

## Karantina / konsinye / sayım / transfer

Karantinadaki mal rezerve ve sevk edilemez. Konsinye/numune fiziksel stoğun içindedir ancak kullanılabiliri azaltır. Sayım farkı elle onaylanır. Transfer çıkış+giriş aynı iş akışında ve temel birimde yazılır.

Her stok yazımı `EnsurePeriodOpen(document_date)` kontrolünden geçer.


## Stock balance kolon sahipliği

Livewire/controller veya genel servis `stock_balances` alanlarını serbestçe update etmez. `quantity`, `reserved`, `quarantine`, `consignment_reserved` için yukarıdaki dedicated writer sınırı korunur ve ilgili integrity komutu kaynak kayıt toplamıyla özeti doğrular.
