# Ekran — Karantina Kontrolü

## Amaç

Satış iadelerinden gelen kullanılmaz stok miktarlarını kısmi olarak satılabilir veya hurda kararıyla sonuçlandırmak.

## Liste

- Ürün
- Lokasyon
- Kaynak İade
- Giriş Miktarı
- Released
- Scrapped
- Pending
- Unit Cost
- Bekleme Süresi
- Durum

## Detay / karar

Kullanıcı pending miktardan:

- Satılabilir miktar
- Hurda miktar
- Karar notu

girer.

Aynı işlemde iki miktar birlikte girilebilir.

## Kurallar

- released + scrapped <= quantity
- release stok hareketi üretmez
- scrap stock out üretir
- pending ürün satılamaz/rezerve/transfer edilemez
- karar optimistic lock kullanır

## Eylemler

- Satılabilir Yap
- Hurdaya Ayır
- Kısmi Karar Uygula
- Kaynak İadeyi Aç
