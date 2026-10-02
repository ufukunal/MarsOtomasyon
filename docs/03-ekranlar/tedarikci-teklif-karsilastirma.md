# Ekran — Tedarikçi Teklif Toplama ve Karşılaştırma

**v65 notu:** Bu iş akışı v65'te hazır ekran olarak bulunmaz. Genel tablo, form, uyarı ve action dili korunur.

## Amaç

Bir satınalma talebi için birden fazla tedarikçiden alınan teklifleri kaydetmek, belge veya satır bazında karşılaştırmak ve K-090/K-091'e göre kullanıcı seçimini kalıcılaştırmak.

## Teklif listesi

- Tedarikçi
- Teklif No
- Para Birimi
- Kur
- Tarih
- Geçerlilik
- Ara Toplam
- KDV
- Genel Toplam
- Seçim Durumu

## Karşılaştırma matrisi

Satırlar talep ürünleridir. Kolon grupları tedarikçi tekliflerini gösterir.

Her hücrede uygun olduğunda:

- teklif miktarı
- birim
- birim fiyat
- iskonto
- net tutar
- teslim bilgisi/notu

gösterilir.

## Seçim modları

### Belge bazlı

Kullanıcı bir tedarikçi teklifini seçer. Uygun teklif satırları request line bazında topluca seçilir.

### Satır bazlı

Her talep satırında farklı tedarikçi teklif satırı seçilebilir.

Sistem otomatik "en ucuz", puan veya kazanan belirlemez.

## Eylemler

- Tedarikçi Teklifi Ekle
- Teklif Düzenle (draft)
- Belgeyi Seç
- Satır Seç
- Seçimi Değiştir (siparişe dönüşmediyse)
- Siparişleri Oluştur

## Yetki

Seçim eylemleri `purchasing.quote.select` iznine bağlıdır. Ayrı approval state veya tutar eşiği yoktur.

## Sipariş üretimi

Satır bazlı seçimlerde sistem seçili satırları tedarikçiye göre gruplar ve her tedarikçi için ayrı satınalma siparişi üretir.

## Audit

Seçim/değişiklikte:

- request line,
- eski supplier quote line,
- yeni supplier quote line,
- actor,
- timestamp

period audit'e yazılır.
