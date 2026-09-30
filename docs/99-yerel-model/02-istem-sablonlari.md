# İstem şablonları

## Yeni görev

```
Aşağıdaki görev dosyasını uygula. Şemayı birebir kullan, değiştirme.
Yalnızca "Dokunulacak dosyalar" listesindeki dosyalara yaz.
Başka hiçbir dosyaya dokunma. Kod dışında açıklama yazma.

--- GÖREV DOSYASI ---
<dosyanın tam içeriği>
```

## Hata düzeltme

```
Şu görevi uyguladın: <G-xxx>
Kabul ölçütü başarısız. Hata:

<hata çıktısı>

İlgili dosya:
<dosya içeriği>

Yalnızca bu hatayı düzelt. Başka değişiklik yapma.
```

## Test yazma

```
Şu dosya için Pest testi yaz:
<dosya içeriği>

Test edilecek davranışlar:
- <madde>
- <madde>

RefreshDatabase kullan. Factory gerekiyorsa oluştur.
```

## Gözden geçirme

```
Aşağıdaki kodu şu kurallara göre denetle:
1. BelongsToCompany trait'i var mı (iş modeliyse)
2. Tutar decimal(18,4), miktar decimal(18,3) mi
3. Ham SQL veya DB::table() kullanılmış mı
4. İş kuralı Livewire bileşenine yazılmış mı (yazılmamalı)
5. Yetki kontrolü Action içinde var mı

Yalnızca bulduğun sorunları listele, kod yazma.

<kod>
```
