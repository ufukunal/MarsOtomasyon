# Faz 2 — Stok

Tüm Faz 2 tabloları PERIOD DB'dedir; company_id yoktur. Ürün/lokasyon/birim kartları aynı DB'de gerçek FK ile bağlanır.

## Görevler

G-201 tablolar · G-202 RecordStockMovement · G-203 hareketli ortalama · G-204 stok durumu · G-205 stok hareketleri · G-206 transfer · G-207 ambar fişi · G-208 sayım · G-209 karantina · G-210 rezervasyon · G-211 açılış · G-212 test.

## Bitiş ölçütü

- [ ] Stok yalnız RecordStockMovement ile değişiyor
- [ ] stock_movements miktarı her zaman temel birimde
- [ ] conversion_factor eksikse işlem engelleniyor
- [ ] Money/BCMath kullanılıyor
- [ ] moving average doğru ve eşzamanlı işlemlerde bozulmuyor
- [ ] stock_balances doğrudan business write almıyor
- [ ] rezervasyon fiziksel stoğu düşürmüyor
- [ ] rezervasyon kullanılabilir stoktan fazla oluşturulmuyor
- [ ] karşılanamayan sipariş miktarı açık kalıyor
- [ ] aynı satır birden fazla lokasyona reservation kayıtlarıyla dağıtılabiliyor
- [ ] sevk/irsaliye rezervi çözüp stok düşürmeye hazır contract sağlıyor
- [ ] ambar fişi koli etiketi 1/N bilgisi ambar fişi kolilerinden üretiliyor
- [ ] karantina/konsinye kullanılabilir stoğu doğru azaltıyor
- [ ] sayım farkı manuel onaysız hareket üretmiyor
- [ ] integrity:stock/costs/reservations/quarantine/units geçiyor
- [ ] cost.view olmayan payload/export maliyet içermiyor
- [ ] gerçek PostgreSQL testleri geçiyor
