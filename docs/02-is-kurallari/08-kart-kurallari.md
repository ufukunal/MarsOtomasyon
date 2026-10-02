# Kart kuralları (cari ve ürün)

## Kod üretimi

- Cari: `CR` + 7 hane sıra (`CR0000036`); tedarikçi de aynı seriden
- Ürün: elle girilir (avize kodları anlamlı: `AVZ-2032`)
- Kod şirket içinde benzersiz, kayıt sonrası **değiştirilemez**
- Şirketler arası kopyalamada hedefte aynı kod varsa otomatik suffix/overwrite yapılmaz; kullanıcı **mevcut kartı kullan / yeni kod gir / iptal** seçeneklerinden birini seçer

## Cari

- Müşteri/tedarikçi ayrımı **kategoriyle** yapılır, ayrı kart açılmaz
- Bir kart hem müşteri hem tedarikçi olabilir; bakiye tektir
- `term_days` boşsa şirket varsayılanı (30 gün)
- Risk limiti aşımı **uyarı**, engel değil
- İskonto yüzdesi belgelere otomatik gelir, belgede değiştirilebilir

## Ürün

- **Her varyant ayrı kart.** Gold 80 cm ve Krom 60 cm ayrı koddur.
- Varyant grubu kartları bir arada gösterir; stok, fiyat ve barkod
  **kart seviyesindedir**
- `allow_negative_stock` ürün bazındadır; izinliyse uyarı verilir,
  izinsizse işlem engellenir
- Fiyat **KDV hariç** saklanır; girişte dahil/hariç seçilebilir

## Set ürün

- Kendi stoğu **yoktur**
- Satılabilir adet: `min(bileşen_kullanılabilir_stoğu ÷ gereken_miktar)`
- Bir bileşen tek seti karşılayamıyorsa set adedi sıfırlanır ve
  **tüm kanallarda** satışa kapanır
- Satışta bileşenler stoktan düşer, set düşmez
- Set ürün başka bir setin bileşeni olamaz (iç içe set yok)

## Konfigüratör

- Seçim grupları ürüne bağlanır (Gövde, Kristal, Duy)
- Konfigüratör seçeneği **fiyat taşımaz ve satış fiyatını değiştirmez**; seçenek yalnız ürün özelliği/konfigürasyon bilgisidir
- Sipariş satırında seçim **dondurulur**; sonradan tanım değişse
  eski sipariş bozulmaz

## Fiyat listesi

- Birden çok liste olabilir; biri varsayılan
- Satırda tarih aralığı olabilir (kampanya)
- Listede fiyatı olmayan ürün için `products.list_price` kullanılır
