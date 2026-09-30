# Faz 0b — Arayüz bileşen kütüphanesi

## Neden ayrı bir faz

Filament kullanmıyoruz. Filament'in bedavaya verdiği tablo, form ve modal
davranışlarını bir kez kendimiz yazıyoruz; sonra 300+ ekranda kullanıyoruz.

Bu faz atlanırsa her ekranda aynı tablo mantığı tekrar yazılır — prototipteki
91 katmanlı yamanın oluşma biçimi tam olarak budur.

## Görevler

| No | Görev |
|---|---|
| G-0b1 | Tema dosyası — tek CSS, değişkenler, temel öğeler |
| G-0b2 | Tablo bileşeni — arama, sıralama, filtre, sayfalama, seçim, dışa aktarma |
| G-0b3 | Form bileşenleri, modal, bildirim, onay kutusu |

## Bitiş ölçütü

- [ ] `resources/css` altında **tek** dosya var
- [ ] Tablo bileşeni tek satır tanımla çalışıyor
- [ ] Yeni bir liste ekranı yazmak 20 satırı geçmiyor
- [ ] Ekran bazında CSS veya JS dosyası **yok**
