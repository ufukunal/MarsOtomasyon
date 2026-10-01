# MarsOtomasyon — Şartname v2

Bu belge özet şartnamedir; ayrıntıda karar günlüğü ve konu belgeleri üstündür.

## Sistem

Avize toptan ticareti için şirket içi ERP: cari, stok, satış, alış, kasa/banka/çek-senet, iade, ithalat, basit üretim/fason, e-ticaret.

Kapsam dışı: genel muhasebe, e-belge/GİB, lot/parti, bütçe, amortisman, ileri MRP/OEE/kapasite, katalog, kalite modülü.

## Teknoloji

Laravel 13 + Livewire 3 + kendi bileşenleri + düz CSS; PostgreSQL Master + şirket/yıl period DB; Valkey. Filament yok.

## Veri mimarisi

Master yalnız üst bağlam/yetki/ayar/print profile/kopyalama izni. Kartlar ve operasyon period DB'de. Period tablolarda company_id/global scope yok. Aynı period ilişkilerinde gerçek FK; Master actor cross-DB FK yok.

## İş çekirdeği

- hareketli ortalama maliyet
- stok temel birimde ve yalnız RecordStockMovement
- posted değişmez, ters kayıt
- ay kilidi document_date
- cari bakiye contact_transactions
- yaşlandırma sanal FIFO
- irsaliye sevk+stok; fatura cari
- rezervasyon kullanılabilir stok kadar, çok lokasyona dağıtılabilir
- çek/senet teslimde cari etkisi; tahsilde ikinci etki yok
- risk uyarı, blok değil
- Money+BCMath
- gerçek PostgreSQL test + integrity

## Dönem

Aynı şirket yıl devrinde kart ve gerekli stok bakiye kimlikleri/kodları korunur. İşlem geçmişi taşınmaz. Devir sonunda kullanıcı period erişim/yetkisi kopyalama sorulur.
