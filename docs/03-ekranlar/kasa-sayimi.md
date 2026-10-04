# Ekran — Kasa Sayımı

## Amaç

K-094'e göre kasanın sistem bakiyesi ile fiziksel toplamını karşılaştırmak ve gerekirse kontrollü fark hareketi üretmek.

## Form

- Kasa
- Sayım Tarihi
- Sistem Bakiyesi
- Fiili Bakiye
- Fark
- Gerekçe

Sistem bakiyesi bilgi amaçlı ekranda gösterilebilir; confirm anında yeniden hesaplanır.

Kupür alanları yoktur.

## Fark

```
fark = fiili - sistem
```

- 0: hareket yok.
- pozitif: kasa farkı in.
- negatif: kasa farkı out.

Fark sıfır değilse gerekçe zorunlu.

## Eylemler

Taslak:

- Kaydet
- Onayla

Confirmed:

- Fark Belgesini Aç — varsa

## Kurallar

- Confirm sonrası sayım immutable.
- Sayım eski cash movements kayıtlarını değiştirmez.
- Confirm anında sistem bakiyesi tekrar hesaplanır.
- Stale version reddedilir.
