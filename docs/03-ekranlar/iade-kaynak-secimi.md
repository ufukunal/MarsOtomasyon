# Ekran — İade Kaynak Seçimi

## Amaç

İadenin aynı dönem, önceki dönem veya kaynaksız manuel kaynağını açıkça seçmek.

## Aynı dönem

- cari seç
- posted satış/alış faturalarını listele
- satır bazında iade edilebilir kalan miktarı göster
- seçilen satırları iade taslağına aktar

## Önceki dönem

- erişilebilen eski period seç
- posted fatura ara
- kaynak document/line kimliği ve frozen değerler snapshot edilir
- eski period mutate edilmez
- cross-DB FK kurulmaz

## Kaynaksız

- ayrı yetki kontrolü
- gerekçe zorunlu
- cari + ürün + miktar manuel
- fiyat/KDV manuel

## Kısmi iade

Kaynak satırda:

```
kalan = kaynak miktar - etkin posted iadeler
```

gösterilir.

Reverse edilmiş iadeler tekrar iade edilebilir miktarı açar.
