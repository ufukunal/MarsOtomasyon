# G-204 — Stok Durumu ekranı

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Ürün ve lokasyon bazında anlık stok görünümü.

## Önkoşul
G-202, G-0b2


## Dokunulacak dosyalar
- `database/migrations/period/`


## Şema / Kod

Mevcut şema/kod örnekleri aşağıdaki kanonik stok sözleşmesiyle birlikte uygulanır; çelişkide kanonik sözleşme üstündür.

## Kolonlar

| Kolon | Kaynak | Not |
|---|---|---|
| Ürün | `products.code` + ad | |
| Lokasyon | `locations.name` | |
| Stok | `stock_balances.quantity` | sağa hizalı |
| Rezerve | `reserved` | |
| Konsinye | `consignment_reserved` | |
| Karantina | `quarantine` | |
| Kullanılabilir | hesaplanır | **kalın**, negatifse kırmızı |
| Minimum | `products.min_stock` | |
| Durum | hesaplanır | rozet: Yeterli / Kritik / Tükendi |
| Birim Maliyet | `product_costs.moving_average` | **`cost.view` izni gerekir** |
| Stok Değeri | miktar × maliyet | **`cost.view` izni gerekir** |

## Kullanılabilir

```
kullanılabilir = quantity − reserved − consignment_reserved − quarantine
```

## Durum rozeti
- Tükendi: kullanılabilir ≤ 0
- Kritik: kullanılabilir ≤ min_stock
- Yeterli: diğer

## Filtreler
Lokasyon, kategori, marka, durum (tükenen / kritik / yeterli),
"yalnızca stoğu olanlar"

## Toplam satırı
Toplam kullanılabilir miktar ve **toplam stok değeri** (izin varsa).

## Kritik kural
`cost.view` izni yoksa maliyet ve değer kolonları **kolon tanımına
eklenmez**. Gizlenmez — üretilmez. Dışa aktarmada da yer almaz.


## Kurallar

### Göreve özel kararlar
- Kullanılabilir = quantity - reserved - consignment_reserved - quarantine.
- Set ürün kullanılabilirliği bileşenlerden anlık türetilir.


### Uygulama ayrıntıları
- Kullanılabilir stok = fiziksel quantity − reserved − consignment_reserved − quarantine.
- Stok durumu product + location bazında aktif period DB'den okunur.
- Set ürün kullanılabilirliği ayrı fiziksel set bakiyesi yerine bileşen stoklarından türetilir.
- Maliyet kolonları `cost.view` yoksa query/payload seviyesinde üretilmez.

## Kabul ölçütü
- Kullanılabilir doğru hesaplanıyor
- Karantinadaki miktar kullanılabilirden düşülüyor
- `cost.view` izni olmayan kullanıcıda maliyet kolonu HTML çıktısında yok
- Dışa aktarmada da yok
- Toplam satırı filtreye uyuyor


## İstem
> StockStatus Livewire ekranını DataTableComponent üzerine yaz. Kolonlar
> yukarıdaki tabloya göre olsun. Kullanılabilir hesaplansın. cost.view izni
> yoksa maliyet ve stok değeri kolonlarını kolon dizisine EKLEME.
