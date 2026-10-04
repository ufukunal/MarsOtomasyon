# Ekran — Tedarikçi Ödeme

## Amaç

K-093'e göre tedarikçiye kasa veya bankadan ödeme yapmak; fatura settlement zorunluluğu oluşturmadan cari borcu azaltmak.

## Form

- Tedarikçi
- Tutar
- Kasa/Banka
- Hesap
- document_date
- opsiyonel kaynak alış faturası
- açıklama/not

## Davranış

- Ana giriş noktası genel Tedarikçi Ödeme formudur.
- Alış faturası ekranındaki `Ödeme Yap` kısayolu bu formu açar.
- Kısayolda supplier ve source purchase_invoice hazır gelir.
- Kullanıcı tutarı değiştirebilir.
- Kaynak fatura zorunlu değildir.
- Kısmi ödeme serbesttir.

## Posting

Post:

- supplier contact transaction = debit,
- cash/bank movement = out.

Fatura üzerinde paid/remaining settlement kolonu oluşturulmaz.

## Eylemler

- Post Et
- Reverse
- Kaynak Faturayı Aç — varsa

## Uyarılar

- Pasif hesap seçilemez.
- Hesap para birimi Faz 5 ödeme kuralıyla uyumlu olmalıdır.
- Dönem kapalıysa posting yapılamaz.
