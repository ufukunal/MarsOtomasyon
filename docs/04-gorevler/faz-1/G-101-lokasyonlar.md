# G-101 — Lokasyonlar

## Amaç
Stok tutulan yerler: **depo, şube, araç**. Araç sıcak satışta kullanılır.

## Şema
`docs/01-veri-modeli/12-locations.md` içindeki şemayı birebir uygula.

## Ekran
Liste: kod, ad, tip (rozet), plaka (araçsa), varsayılan, durum.
Form: kod, ad, tip seçimi, araçsa plaka alanı açılır, adres, varsayılan, durum.

## Kurallar
- Her şirkette en az bir depo ve bir varsayılan lokasyon olmalı
- Varsayılan lokasyon silinemez
- Hareketi olan lokasyon silinemez, pasife alınır
- Araç lokasyonu depo gibi davranır; ayrı mantık yoktur

## Kabul ölçütü
- Üç tip de açılabiliyor, araçta plaka alanı çıkıyor
- Varsayılan işaretlenince eskisi kalkıyor
- Farklı şirketin lokasyonu görünmüyor

## İstem
> locations tablosu için migration, Location modeli, LocationKind enum'u,
> liste ve form ekranlarını yaz. Tip vehicle seçilince plaka alanı görünsün.
> Varsayılan lokasyon tekil olsun.
