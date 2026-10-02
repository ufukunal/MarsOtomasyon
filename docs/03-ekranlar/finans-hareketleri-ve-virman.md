# Ekran — Finans Hareketleri ve Virman

**v65 notu:** Faz 5'e özgü hazır ekran referansı yoktur. Genel MarsOtomasyon liste/form/action dili kullanılır.

## Amaç

Kasa ve banka hesap hareketlerini tek görünümde izlemek ve K-092'ye göre tüm aynı-para-birimli virman kombinasyonlarını oluşturmak.

## Hareket listesi

Filtreler:

- tarih aralığı
- hesap türü: kasa | banka
- hesap
- yön: in | out
- belge tipi
- cari
- mutabakat durumu — banka hareketlerinde

Kolonlar:

- Tarih
- Hesap
- Hesap Türü
- Belge No
- İşlem Türü
- Cari
- Giriş
- Çıkış
- Para Birimi
- Açıklama
- Mutabakat

## Virman formu

- document_date
- source account type
- source account
- target account type
- target account
- amount
- note

## Doğrulama

- source ve target aynı fiziksel hesap olamaz.
- source.currency = target.currency zorunlu.
- amount > 0.
- Faz 5 virmanında kur alanı gösterilmez.
- Cari seçilmez.

## Eylemler

- Virman Oluştur
- Reverse
- Belgeyi Aç

## Posting özeti

Tek finance_transfer:

- source out,
- target in,
- cari etkisi yok.

İki finans hareketinden biri başarısızsa tüm işlem rollback olur.
