# MarsProject 20 HTML ekranı → Livewire kaynak entegrasyonu (17 Ekim 2026)

## Kaynak ve yöntem

HTML referansı: Kullanıcı tarafından sohbet ekinde verilen `MarsProject_20_Ekran_Cari_Virman_Tek_HTML.html`.
Kaynak uygulama: `ufukunal/MarsOtomasyon` PHP/Laravel/Livewire `main` tabanı
(`66669028e05b042cfc3847a9814e89177b4afabe`).

Bu çalışma, kullanıcı arayüzü ve muhasebe akışlarını **kaynak koduna uygular**.
Yalnız rota mevcudiyeti, görsel parity ve izole PostgreSQL testi birbirinden farklı kabul kapılarıdır.
Orijinal dış prototip bu depoya kopyalanmamıştır; güvenli finansal işlemler gerçek sunucu
aksiyonları üzerinden çalışır ve tarayıcıdaki önizleme hiçbir zaman deftere yazmaz.

## 35 maddelik kaynak uygulama / açık kabul matrisi

| No | İş | Kaynak kod durumu | Bağımsız kabul |
|---|---|---|---|
| D01 | 5 kolon belge formu | Uygulandı, responsive | Tarayıcı |
| D02 | Tür-bazlı üst bilgi | `ui_meta` JSONB ile kaydedilen alanlar eklendi | Alanların iş-akışına etkisi ayrıca değerlendirilir |
| D03 | Cari arama/seçme | Sunucu araması korunuyor; tek kontrol birebir değil | UX görsel kabul |
| D04 | 10 kolon ve KDV dahil | Uygulandı, KDV bilgi amaçlı | Tutar/stil |
| D05 | Hizmet satırı/detay | Eklendi | UI test |
| D06 | Barkod/hızlı giriş | Backend kod/ad/barkod araması + klavye kısayolu | Browser focus |
| D07 | Yatay 5 özet kartı | Eklendi; hesap sunucuda | KDV |
| D08 | Satış para birimi | TRY-only korunarak okunur gösterildi; alış döviz/kur korundu | İş kuralı |
| D09 | Belge başlık/çıktı | Gerçek PDF/önizleme korunuyor | Pixel/print |
| D10 | Bağımsız alış irsaliyesi | Korundu: sadece onaylı siparişten oluşturulur | Kullanıcı iş-kuralı kararı gerekli |
| D11 | Mal Kabul | Korundu: kaynak belge ve gerçek stok hareketi | DB |
| D12 | Tahsilat/tediye bakiye kartları | Eklendi, gerçek cari ve kasa/banka verileri | DB |
| D13 | Dekont/ref | `ui_meta.receipt_reference` kaydı eklendi; belge no otomatik | DB |
| D14 | Tahsilat/tediye | Kartlar, döviz ve ilişik fatura korunuyor | UI |
| D15 | Cari virman bilanço | Borçlu kırmızı / alacaklı yeşil, tahmini tutarlar | DB |
| D16 | Virman ref | Ayrı `contact_transfers.reference` sütunu | Migration |
| D17 | Aşım onayı | Yetki + gerekçe + iki cari atomik işlem korunuyor | DB |
| D18 | Çek/senet KPI | SQL agregasyonu ile 4 kart | DB |
| D19 | Çek/senet filtre | Sunucu SQL search/yön/durum filtresi | DB |
| D20 | Cari etkisi sütunu | İlk kayıt yön/tutar gösterimi | Defter çapraz doğrulaması |
| D21 | Satır bordro eylemleri | Duruma göre Ciro/Bankaya Ver/Tahsil/İade/Öde eklendi | E2E |
| D22 | 500 satır limiti | `paginate(25)` ve veri kaybı olmayan filtre | DB |
| D23 | Çek/senet kayıt etkisi | Başlangıç cari ve banka etkisi paneli | DB |
| D24 | Evrak cari araması | 35 kayıt sunucu limitli arama, seçili cari korunuyor | UI |
| D25 | Senet lehtar/borçlu | Cari seçimi ayrı anlamsal etiketle gösterilir; banka adı farklı kalır | Veri sözlüğü |
| D26 | Tekli/toplu ciro | Tek evrak select + mevcut toplu seçim korunur | E2E |
| D27 | Ciro hedef cari etkisi | Hedef bakiye kartları; ikinci kez ilk hareket yazılmaz | DB |
| D28 | Çek/senet mali düzeltme | Yetkili ters ilk hareket + yeni evrak; bordro görmüş evrak reddedilir | **P0 DB güvenlik testleri** |
| D29 | Ortak menü/kontrol CSS | Scoped CSS uygulandı, diğer ERP modülleri korunuyor | Pixel |
| D30 | 5 viewport görsel kabul | **Yapılmadı** | UAT |
| D31 | Finans PostgreSQL senaryoları | **Çalıştırılmadı** | İzole TEST |
| D32 | Belge mali/stok regresyonları | **Çalıştırılmadı** | İzole TEST |
| D33 | Barkod SQL | Önceden vardı, tek kontrol üzerinden kullanılıyor | DB |
| D34 | Çek/senet rozetleri | Duruma göre warning/info/success/danger tonu ve PHP formatter uygulandı | Pixel |
| D35 | Klavye/focus/ARIA | Etiketler/focus shortcut eklendi, kapsamlı a11y testi yapılmadı | Browser |

## Ana kontrol sınırları

- Canlı PostgreSQL'e test amaçlı veri yazılmadı; prod VM/container güncellemesi yapılmadı.
- `ui_meta` ve `contact_transfers.reference` için iki yeni `period` migration mevcut;
  migration tamamlanmadan yeni web kodu çalıştırılmamalıdır.
- Eski `securities` mali alanları yerinde değişmez. `ReplaceSecurity` yalnız bordro görmemiş
  `portfolio|issued` evraklarda ters cari hareketi + yeni evrak oluşturur. Canlı kullanıcıya
  açılmadan önce idempotency, paralel istek, fatura/cari toplamı ve rollback doğrulanmalıdır.
- İndirim/KDV satır önizlemesi bilgi amaçlıdır; gerçek son toplam her işlemde backend hesaplayıcıdan çıkar.
- Manual GitHub workflow'lar kendiliğinden çalışmaz. `v65-source-quality.yml` ve
  `v65-isolated-test-run.yml` ayrı çalıştırılmalıdır. İzole TEST testleri için açık onay/erişim gereklidir.

## Kaynak dosya grupları

- `resources/views/livewire/{sales,purchases}/document-editor.blade.php`
- `app/Livewire/{Sales,Purchases}/Base*DocumentEditor.php`
- `resources/views/livewire/finance/{collection-form,contact-transfer-form,securities-center}.blade.php`
- `app/Livewire/Finance/SecuritiesCenter.php`
- `app/Actions/Finance/{ReplaceSecurity,PostContactTransfer,PostCollection}.php`
- `app/Actions/Purchases/PostPayment.php`
- `app/Livewire/Concerns/WithFinancialRecordCorrection.php`
- `resources/css/v65-shell.css`, `resources/js/app.js`
- `tests/Unit/UI/{MarsHTML20ParityContractTest,V65DocumentEditorPhase2Test}.php`


## 17 Ekim tam denetim bulguları

- Onaylı 20 ekranlı HTML prototipi Chromium'da 1440×900 ve 390×844: 40/40 açılış, 0 sayfa JavaScript hatası.
- Orijinal dokuz belge formunda 10 sütun; iki çek/senet listesinde 9 sütun vardı. Önceki 11/10 sütun raporu yanlıştı. Denetim dalındaki kaynak patch bunu düzeltiyor.
- Tahsilat/tediye ortak trait'i yalnız tediye formunda bulunan kur/para birimi alanlarına erişiyordu: PHPStan riski. Kur/para birimi hook'a taşındı.
- Finansal hesap seçilmediğinde yanıltıcı negatif bakiye; yetkisiz bakiye önizlemesi; tüm modülleri etkileyen CSS root override'ı kaynak patch ile giderildi.
- 16 Ekim son izole TEST başarısızlığı: eksik dedicated PostgreSQL TEST password (exit 77). Eski QA quality job'u başarılıydı; 17 Ekim merge SHA'sında yeni başarılı QA yok.
- Operasyonel depo/teslim ui_meta alanlarının tamamı stok defterini yönetmiyor. Bağımsız alış irsaliyesi/Mal Kabul source zorunluluğu korunuyor.
- ReplaceSecurity güncel işlem tarihiyle tekrar düzenleme yapıyor; eski vadeli evrak düzeltme senaryosu sınırlı. Ayrıca gerçek DB rollback/idempotency kabulü yok.
- Denetim dalı: fix/html20-full-audit-20261017. Bu çalışma PHPStan/DB/E2E testlerinin çalıştırıldığını göstermez.


## 17 Ekim — ikinci kaynak kontrolü ve yetki düzeltmesi

- Kontrol edilen `main`: `54ddae0667eef88bd5c4ba350e72875c777ea724`. Kaynakta PR #7 düzeltmeleri mevcuttu.
- Satış/alış dokuz belge ekranının 10 kolonlu tablo işaretleri, 20 işlem rota adı ve kayıt metaverisinin varlığı kaynak düzeyinde yeniden doğrulandı.
- Kaynak kontrat kontrolünde 45/45 metin/rota/tablo işareti eşleşti. Bu sayı çalıştırılmış PHP Unit testi olarak yorumlanmamalıdır.
- Tahsilat ve tediye finansal önizlemelerinin public Livewire `contactBalance()` / `projectedFinancialContactBalance()` metotlarında `contacts.view` yetkisi yoktu. Doğrudan metot çağrısı ve üç görünür alan artık yetki koruması kullanıyor; yetki yoksa gerçek bakiye yerine açıklayıcı metin gösteriliyor.
- Hesap seçilmediğinde gerçekte 0 bakiye varmış gibi gösteren önizleme yerine hesap seçme uyarısı gösteriliyor.
- Yetki kontrolü `auth()->check()` + `auth()->user()->can(...)` ile yazılarak PHPStan'in nullable user tipiyle ilgili geçmiş uyarılarına benzer yeni ihlaller önlendi.
- Eklenen kaynak kontrat testleri: `tests/Unit/UI/MarsHTML20ParityContractTest.php`.
- **Kabul edilmemiş kapılar:** bu commit için PHP lint, Laravel Blade compile, Pint, PHPStan, izole PostgreSQL, finansal işlemlerin gerçek kaydı, tarayıcıda PHP piksel eşitliği ve production sunucuya dağıtım henüz çalıştırılmadı/doğrulanmadı.
- GitHub workflow'ları `workflow_dispatch` üzerinde manuel onaya bağlıdır; bunlar yalnız kod birleştirildi diye tetiklenmez.
