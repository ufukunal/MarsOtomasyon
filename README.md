# MarsOtomasyon

Avize toptan ticareti için firmaya özel ERP. Ön muhasebe, cari, kasa/banka/çek-senet, stok, satış, alış, iade, ithalat, basit üretim, fason ve e-ticaret entegrasyonlarını kapsar.

## Kaynak önceliği

1. Kullanıcının son açık kararı
2. `GUNCELLEME-PROMPT.md`
3. `docs/05-karar-gunlugu/kararlar.md`
4. `docs/00-genel/07-veritabani-mimarisi.md`
5. Güncel veri modeli / iş kuralı / görev belgeleri
6. `DEVIR-PROMPT.md` — üsttekilerle çelişmeyen kısımlar
7. `reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html` — UI/terminoloji/görsel referans

## Güncel mimari

- PHP 8.3+, Laravel 13, Livewire 3, kendi bileşenlerimiz, düz CSS.
- PostgreSQL: `MarsProject_Master` + her şirket/yıl için ayrı period DB.
- Kartlar dahil yıllık işletme verileri period DB'dedir; period tablolarında `company_id` ve şirket global scope'u yoktur.
- Master: şirket/dönem/kullanıcı/yetki, kur/ayar, `company_copy_permissions`, `print_profiles`, document/report template-preset/export/print operasyon metadata'sı, kanal hesapları + dönemler arası external-event registry, deployment/backup/restore/health metadata ve master audit.
- Aynı period DB içindeki ilişkiler gerçek FK kullanır; Master kullanıcı gibi cross-DB referanslarda gerçek FK kurulmaz.
- Dağıtım `migrate:periods` ile tüm period DB'leri günceller.

Repo şu anda şartname/görev deposudur. Kod, görev dosyaları uygulanırken üretilecektir.


## Kodlama öncesi durum

Plan/dokümantasyon fazları tamamlandı; ancak **A-125** (non-stock service purchase invoice satır modeli) ve **A-126** (fason hizmet maliyetinin kısmi completion'lara dağıtımı) açık kodlama blokajlarıdır. Bu kararlar kapanmadan Faz 4/7/8'in ilgili görevleri uygulanmaz.

Dönem devrinde K-256 gereği açık `sales_order` ve `purchase_order` yalnız kalan miktarlarıyla yeni period'da yeni confirmed snapshot olarak oluşturulur; aktif satış rezervasyonları location bazında yeniden kurulur. Açık quarantine de taşınır.
