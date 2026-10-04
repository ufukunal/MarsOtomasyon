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

- PHP ^8.4, Laravel 13, Livewire 3, kendi bileşenlerimiz, düz CSS.
- PostgreSQL: `MarsProject_Master` + her şirket/yıl için ayrı period DB.
- Kartlar dahil yıllık işletme verileri period DB'dedir; period tablolarında `company_id` ve şirket global scope'u yoktur.
- Master: şirket/dönem/kullanıcı/yetki, kur/ayar, `company_copy_permissions`, `print_profiles`, document/report template-preset/export/print operasyon metadata'sı, kanal hesapları + dönemler arası external-event registry, deployment/backup/restore/health metadata ve master audit.
- Aynı period DB içindeki ilişkiler gerçek FK kullanır; Master kullanıcı gibi cross-DB referanslarda gerçek FK kurulmaz.
- Dağıtım `migrate:periods` ile tüm period DB'leri günceller.

Repo aktif uygulama kodunu ve kanonik dokümantasyonu birlikte içerir. Faz 0, Faz 1 ve Faz 2 production kodu uygulanmıştır; kalite kapısı frontend build, Pint, Larastan ve Pest kontrollerinden oluşur.


## Güncel durum

Plan/dokümantasyon fazları tamamlandı. A-125 K-257 ile, A-126 K-258 ile, A-127 K-256 ile kapatıldı; açık ürün kararı yoktur.

Faz 0–2 uygulama katmanı tamamlanmıştır. State-changing Livewire istekleri K-038 gereği snapshotta taşınan idempotency key ile çalışır; Period mutationları period DB'deki, Master mutationları Master DB'deki idempotency kayıtlarını kullanır. Ayrıntılı görev kalite raporu: `docs/00-genel/10-gorev-kalite-denetimi.md`.

Dönem devrinde K-256 gereği açık `sales_order` ve `purchase_order` yalnız kalan miktarlarıyla yeni period'da yeni confirmed snapshot olarak oluşturulur; aktif satış rezervasyonları location bazında yeniden kurulur. Açık quarantine de taşınır.


## Yerel demo seed

Demo şirket/kullanıcı seed'leri production ortamında çalışmaz. Yerel veya test ortamında demo admin oluşturmak için `DEMO_ADMIN_PASSWORD` en az 12 karakter olarak açıkça verilmelidir; sabit varsayılan parola yoktur. E-posta `DEMO_ADMIN_EMAIL` ile değiştirilebilir.

## Backup bildirimleri

Backup e-posta bildirimleri `BACKUP_NOTIFICATION_EMAIL` ile yapılandırılır. Local/test için `backup@mars.test` güvenli sentinel adresidir; production bu sentinel, boş veya geçersiz adresle boot etmez ve gerçek bir bildirim adresi zorunludur.
