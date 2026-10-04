# Ekran — Çek / Senet

## Amaç

K-096/K-097 çek-senet yaşam döngüsünü, cari etkilerini ve banka operasyon bilgilerini tek kıymet detayında izlemek.

## Liste

Filtreler:

- tür: çek | senet
- yön: alınan | verilen
- durum
- cari
- vade aralığı
- banka

Kolonlar:

- Tür
- Yön
- Seri / No
- Cari
- Keşideci / Düzenleyen
- Banka
- Vade
- Tutar
- Durum
- Son İşlem

## Yeni kıymet

Temel alanlar:

- çek/senet türü
- alınan/verilen
- seri/no
- tutar
- vade
- para birimi
- ilk cari
- keşideci/düzenleyen
- banka
- şube
- açıklama

K-013 gereği yeni FX davranışı açılmaz.

## Detay sekmeleri

- Bilgiler
- İşlem Geçmişi
- Cari Etkiler
- Banka İşlemleri
- Notlar
- Timeline

## Alınan kıymet eylemleri

Duruma göre:

- Portföye Al
- Ciro Et
- Tahsile Ver
- Tahsil Et
- Karşılıksız / Geri Döndü

### Ciro

- Karşı cari zorunlu
- Tarih
- Açıklama

Original cari ikinci kez etkilenmez.

### Tahsile verme

- Banka hesabı
- Banka teslim tarihi
- Referans
- Açıklama

### Tahsil

- Banka hesabı
- Tahsil referansı
- Tarih

## Verilen kıymet eylemleri

- Ödeme Bekliyor
- Ödendi
- Geri Döndü / İptal

Ödendi işleminde banka hesabı + ödeme referansı tutulabilir.

## Karşılıksız / protesto

- detay açıklaması
- tarih
- banka/reference bilgisi

saklanabilir.

## History

Her geçiş ayrı security_event olarak görünür. Önceki event silinmez veya değiştirilmez.

## Risk göstergesi

Alınan kıymet `portfolio` veya `sent_to_collection` durumundaysa risk bileşeni olarak işaretlenir.

Endorsed/collected/bounced durumları portföy riskine dahil edilmez.
