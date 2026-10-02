# Ekran — Satınalma Talebi

**v65 notu:** Onaylı v65 prototipinde doğrudan satınalma ekranı yoktur. Bu ekran mevcut genel MarsOtomasyon görsel/etkileşim diliyle tasarlanır; prototipte varmış gibi özel route/tab adı uydurulmaz.

## Amaç

İhtiyaç duyulan ürün ve miktarları tedarikçi seçmeden önce toplamak; istenirse buradan teklif toplama veya doğrudan satınalma siparişi başlatmak.

## Liste kolonları

- Talep No
- Tarih
- Talep Eden
- Satır Sayısı
- Toplam Miktar
- Teklif Durumu
- Sipariş Durumu
- Durum

## Yeni talep

Header:

- document_date
- notes

Satır:

- ürün
- birim
- miktar
- açıklama

Talepte contact zorunlu değildir.

## Eylemler

Taslak:

- Kaydet
- Satır Ekle
- Satır Sil
- Onayla

Onaylı:

- Teklif Topla
- Doğrudan Sipariş Oluştur
- Kapat

## Kurallar

- Draft numarasız olabilir.
- Onayda numara üretilir.
- Stok/cari/maliyet etkisi yoktur.
- Posted/confirmed kayıt yerinde değiştirilmez.
- Teklif veya siparişe dönüşen satırların lineage ilişkisi korunur.
- Yetki yoksa dönüşüm eylemleri görünmez.

## Görünür durum özeti

Her satırda bilgi amaçlı:

- talep miktarı
- teklif alınan miktar
- seçilen teklif
- siparişe dönüşen miktar

gösterilebilir. Bu değerler ayrı denormalize gerçek kaynak değildir; ilişkilerden hesaplanır.
