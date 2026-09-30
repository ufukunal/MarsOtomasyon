# Yetki

## Model

spatie/laravel-permission, **teams = şirket**. Aynı kullanıcı A şirketinde
Satış, B şirketinde Yönetici olabilir.

## İzin adlandırması

`<ekran>.<eylem>` → `contacts.view`, `sales_invoices.create`,
`sales_invoices.cancel`

Eylemler: `view`, `create`, `update`, `cancel`

## Özel izin: `cost.view`

Maliyet ve kâr görme izni. **Satış rolünde yoktur.**

Bu izin olmadan:
- Maliyet, kâr, marj kolonları tabloya **eklenmez** (CSS ile gizlenmez, hiç basılmaz)
- Ürün kartında maliyet sekmesi görünmez
- Kârlılık raporları menüde çıkmaz
- Dışa aktarmada maliyet kolonları yer almaz

**Gizlemek yetmez, üretmemek gerekir.** Gizlenen veri dışa aktarmada,
sayfa kaynağında veya API yanıtında sızar.

## Kontrol noktaları

1. Menü — yetkisiz ekran menüde görünmez
2. Rota — `can:` middleware
3. Bileşen — Livewire `mount()` içinde yetki kontrolü
4. Eylem — Action sınıfının başında yetki kontrolü
5. Policy — model bazında

Dördüncüsü atlanmamalı: kullanıcı ekranı göremese de isteği elle gönderebilir.
