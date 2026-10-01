# Önbellek

Valkey cache/session/queue ayrı Redis DB numaraları kullanır.

## Anahtar

Period verisinin anahtarı daima şirket+dönem bağlamı taşır: `c{company}:y{year}:{key}`. Bağlam yoksa `CacheKey::period()` istisna fırlatır; `c0:y0` üretmez.

Master anahtarları erişim/ayar türüne göre şirket veya global kapsam taşıyabilir.

## Önbelleğe alınabilecek

- Menü/yetki görünümü
- dönem listesi ve erişim metadatası
- print profile çözümleme
- period içindeki kategori/marka/birim listeleri
- period fiyat listesi satırları
- ağır rapor özetleri kısa TTL ile
- döviz kuru

**Kartlar period DB'dedir; ürün/cari/fiyat için master cache kullanılmaz.**

## Kesinlikle cache edilmez

Stok bakiyesi, cari bakiye, belge durumu, rezervasyonun işlem anı miktarı, number_series.

Veri değişince ilgili anahtar hemen invalidate edilir. Çıplak cache key üretimi yerine `CacheKey` kullanılır.
