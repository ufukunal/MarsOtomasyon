# Ekran — Cari Yaşlandırma

Bu ekran K-063 ve K-064 kararlarını görselleştirir. Kalıcı fatura-tahsilat settlement kaydı oluşturmaz.

## Özet

Üst kartlar:

- Vadesi gelmemiş
- 1–30 gün
- 31–60 gün
- 61–90 gün
- 91–120 gün
- 120+ gün
- Toplam açık bakiye

## Satır tablosu

Önerilen kolonlar yalnız mevcut cari hareket verisinden türetilir:

- Cari
- Kaynak Belge
- Belge Tarihi
- Vade
- Orijinal Borç
- FIFO ile Mahsup Edilmiş
- Kalan
- Gecikme Gün
- Dilim
- Durum

## Renk

- **Yeşil:** satır tamamen kapanmış.
- **Sarı:** kısmen kapanmış.
- **Kırmızı:** hiç kapanmamış.

Renk tam %50 gibi bir eşiğe bağlı değildir; herhangi bir kısmi kapanma sarıdır.

## Hesap

Tahsilat/credit hareketleri en eski borçtan başlayarak sanal uygulanır. Bu dağıtım yalnız rapor runtime hesabıdır; DB'ye invoice settlement yazılmaz.

Satırların kalan toplamı cari hareket bakiyesiyle tutarlı olmalıdır.

## Filtreler

Kaynakta desteklenen veriyle:

- Cari
- Vade aralığı
- Yaşlandırma dilimi
- Durum
- Kaynak belge tipi

Çok dönemli görünüm gerektiğinde Faz 11b `MultiPeriodQuery` altyapısı kullanılır.
