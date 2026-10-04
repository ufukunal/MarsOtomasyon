# Belge tasarımcısı ve template

## Tasarım modeli

K-023/K-213:
- section-based,
- serbest drag/drop canvas yok.

Bölümler sıralanır, görünürlük ve izinli ayarlar düzenlenir.

## Revizyon

Template Master DB'dedir.
Değişiklik yeni immutable Rev.N üretir.
Finalized revision yerinde mutate edilmez.

## Token güvenliği

Allow-list token registry:
- company.*
- document.*
- contact.*
- shipping.*
- line.*
- totals.*
- user.*
- allowed domain fields

Kullanıcı:
- SQL
- PHP
- Blade
- arbitrary expression

yazamaz.

## Render

Business hesapları render katmanında yeniden hesaplanmaz.
Belgenin frozen değerleri/render DTO kullanılır.

PDF HTML+CSS → Browsershot.

## Tekrar baskı

Uygun UI:
- Güncel aktif template ile bas
- Orijinal template revision ile bas

Seçim audit/print history'e yazılır.
