# Karar günlüğü

Verilen kararlar ve gerekçeleri. **Kod bu kararlara uyar; kod kararla
çelişirse kod değişir, karar değil.** Karar değişecekse önce buraya işlenir.

| No | Karar | Gerekçe |
|---|---|---|
| K-001 | Laravel + Livewire, kendi bileşenlerimiz | Filament'in görsel dili istenmedi |
| K-002 | Genel muhasebe kapsam dışı | Gayri resmi sistem, ön muhasebe yeterli |
| K-003 | Resmi ve gayri resmi **ayrı şirket** | Tam izolasyon isteniyor |
| K-004 | Şirketler arası yalnız **izinli kopyalama**, kopya kayıt | Canlı bağ izolasyonu deler |
| K-005 | Parti/lot takibi kapsam dışı | İstenmedi; ithalat maliyeti ortalamaya karışacak |
| K-006 | Maliyet: **hareketli ortalama**, tek yöntem | Sadelik |
| K-007 | Alış fiyatı ±%25 saparsa uyarı, engel yok | Hatalı giriş yakalansın, iş durmasın |
| K-008 | İskonto **KDV'den önce**, matrahı düşürür | Fatura standardı |
| K-009 | Fiyat girişinde KDV dahil/hariç seçilir, **hariç saklanır** | Tek doğru kaynak |
| K-010 | Her varyant **ayrı kart**, üstünde grup | Stok ve barkod varyant seviyesinde |
| K-011 | Set ürünün kendi stoğu yok; `min(bileşen/gerekli)` | Bir bileşen bitince tüm kanallarda kapanır |
| K-012 | Dosya **sıkıştırması yok** | Kullanıcı elle yapacak |
| K-013 | Döviz yalnız ithalat ve alış; kur girişte sabitlenir | Kur farkı hesaplanmaz |
| K-014 | Araç = depo; sıcak satışta doğrudan fatura | Tahsilat elle çözülecek |
| K-015 | İade karantinaya girer, kontrolden sonra stoğa | — |
| K-016 | Kesinleşen belge değiştirilemez, ters kayıtla düzeltilir | Tek kayıt güvenliği |
| K-017 | Dönem ay bazında kilitlenir, yalnız Yönetici açar | Geçmiş rakam oynamasın |
| K-018 | Pazaryeri senkronizasyonu 15 dk + elle tetikleme | — |
| K-019 | Görseller platform bazlı **set** olarak tutulur | Kanal başına farklı görsel |
| K-020 | Katalog modülü **yapılmayacak** | Canva ile elle hazırlanacak |
| K-021 | VDS (6 CPU / 8 GB / 55 GB), PostgreSQL + Valkey | Paylaşımlı hosting yerine |
| K-022 | Yazdırma tek arayüz arkasında; taşıyıcı değişebilir | İleride özel tarayıcı kabuğu gelecek |
| K-023 | Belge tasarımcısı **bölüm tabanlı**, sürükle-bırak değil | Sayfa taşması sorunsuz çalışsın |
| K-024 | Dosya diski soyut; dolunca S3 uyumlu servise geçilir | iDrive, Hetzner |

## Açık kararlar

| No | Konu | Neden bekliyor |
|---|---|---|
| A-001 | 15 dk senkronizasyonda çift satış riski | Kanal bazında ayrılan miktar mı, anlık gönderim mi |
| A-002 | Konfigüratör fiyatlaması | Bileşen toplamı mı, ayrı fiyat tablosu mu |
| A-003 | Reçetede fire yüzdesi tanımlansın mı | — |
| A-004 | Konsolide rapor hangi rollere açık | — |
| A-005 | Etiket yazıcısı markası ve etiket boyutları | Faz 10'da gerekli |
| A-006 | Koli etiketi "1/4" numarası hangi belgeye bağlı | — |
| A-007 | Varyant grubunun pazaryerlerinde varyantlı gönderimi | Sonraya bırakıldı |
