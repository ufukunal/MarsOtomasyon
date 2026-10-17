# MarsProject — bütün belgeler düzenlenebilirlik kaynak taraması
Tarih: 2026-10-17
Kaynak dal: `main`, başlangıç commit: `e0240cd419a9bce08106c7bf3fa0d51b72fdbadf`.

## Tarama yöntemi ve kesin kapsam

- GitHub recursive tree: **1.757 dosya**, `truncated=false`; isim düzeyinde tam repo envanteri.
- Laravel rota tanımları: `routes/web.php`, bütün belge editörleri ve liste URL'leri.
- `app/Enums/DocumentType.php`: 18 farklı dönem belge türü, üretim/ambar
  ve finans için ayrı kayıt modelleri.
- Kod içi kayıt/kesinleştirme/ters kayıt noktaları:
  `app/Actions/Documents`, `app/Actions/Sales`, `app/Actions/Purchases`,
  `app/Actions/Returns`, `app/Actions/Finance`, `app/Actions/Stock`,
  `app/Actions/Production`, ilgili `app/Livewire` sınıfları,
  `resources/views/livewire` ve `resources/views/components/v65`.
- `Document::booted()` posted immutability, optimistic locking,
  `DocumentRelation` ve bütün ilgili yetkilendirme yolları.
- 1.757 dosyanın **her satırının** PHP derleyiciyle çalıştırıldığı veya
  uygulama testinin bütün rotaları fiilen açtığı iddia edilmez.
  Kaynak ve arayüz koşulları için `MarsAllDocumentEditabilityAuditTest`
  statik kontrat eklendi; **henüz çalıştırılmadı**.

## Dönem belgeleri — 18/18 durum ve düzenleme rotası

| Belge türü (DocumentType) | Kullanıcı düzenleme yolu | Sunucu bütünlüğü |
| --- | --- | --- |
| `quote` Satış Teklifi | Taslağı yerinde; numaralıda mevcut Revizyon Oluştur | Aynı numara + artan `revision_no`; `ui_meta` taşınır |
| `sales_order` Satış Siparişi | Taslak yerinde; onaylıdan bağımsız düzenleme taslağı | Orijinal onay/rezervasyon/üretim ilişkisi korunur, yeni taslak ayrı işlemdir |
| `dispatch` Satış İrsaliyesi | Taslak yerinde; posted → ters kayıt → düzenleme taslağı | Aktif faturası olan irsaliye önce terslenemez |
| `sales_invoice` Satış Faturası | Taslak yerinde; posted → ters kayıt → düzenleme taslağı | Cari, stok, risk ve KDV değişimleri yeni postta kontrol edilir |
| `proforma` Proforma | Kaynak teklif/siparişi düzenle ve yeniden proforma oluştur | Daha önce düzenlenen/dağıtılan proforma snapshot'ı salt okunur |
| `collection` Tahsilat | Mevcut `WithFinancialRecordCorrection` Düzelt | Nakit/banka+cari etkisi önce terslenir |
| `contact_debit_credit` Cari Borç/Alacak Fişi | Geçmişten Düzenle → Ters Kayıt → yeniden kesinleştir | Gerekçe zorunlu; `ReverseDocument` kullanılır |
| `purchase_quote` Alış Teklifi | Taslak yerinde; onaylıda bağımsız düzenleme taslağı | Siparişe dönüştürülmüş orijinal değişmez |
| `purchase_order` Satınalma Siparişi | Taslak yerinde; onaylı/gönderilmişte düzenleme taslağı | Aktif mal kabul/irsaliye zinciri silinmez |
| `purchase_dispatch` Alış İrsaliyesi | Mal Kabul yoksa aynı numara ile kontrollü yeniden aç | Alış siparişinin kaynak miktarı iki kez ayrılmaz |
| `goods_receipt` Mal Kabul | Taslak yerinde; posted → ters kayıt, kaynak sipariş/irsaliyeden yeniden kabul | Faturalandırılmış kabulun terslenmesi engellenir |
| `supplier_invoice` Alış Faturası | Taslak yerinde; posted → ters kayıt → bağımsız düzenleme taslağı | Üçlü eşleştirme ve maliyet güncelleme doğrulaması |
| `payment` Tediye | Mevcut finansal kayıt düzeltme akışı | Banka/kasa+cari hareketleri ters kayıtla |
| `expense` Gider | Geçmişten Düzenle → Ters Kayıt → yeni gider | Ödeme hesabı, gider kalemi ve KDV önceki belgeden doldurulur |
| `advance` Avans | Geçmişten Düzenle → Ters Kayıt → yeni avans | Banka/kasa ve cari hareketi ayrı ve izlenebilir |
| `advance_return` Avans İadesi | Geçmişten Düzenle → Ters Kayıt → yeni iade | Avans hareketi terslenmeden ikinci kayıt açılmaz |
| `sales_return` Satış İadesi | Taslak satır/miktar/lokasyon/tarih düzenle; posted ters kayıt → yeni iade | Kaynak fatura satırı, miktar, karantina bütünlüğü |
| `purchase_return` Alış İadesi | Taslak satır/miktar/lokasyon/tarih düzenle; posted ters kayıt → yeni iade | Kaynak fatura, maliyet ve fiziksel stok kontrolü |

**Önemli:** “Düzenlenebilir” her zaman **orijinal posted kaydın üzerine
yazmak** değildir. Ters kayıt/bağımsız düzeltme/yeniden belge düzenleme
işlem kapılarıdır. Orijinal posted fişin defteri asla sessiz değiştirilmez.

## Ayrı model/evrak merkezleri

| Ekran/işlem | Düzenleme ve mevcut sınır |
| --- | --- |
| Cari Virman (`ContactTransfer`) | Geçmişten Düzenle → iki cari hareketini tersle → yeni virman. Yetkisiz bakiye önizlemesi maskeli; bakiye doğrulaması backend'de gerçek. |
| Kasa/Banka Manuel Hareketi (`CashMovement/BankMovement`) | Geçmişten Düzenle → ters hareket → yeni hareket; `statement` ve bordro kaynaklı hareketler manuel yolla değiştirilemez |
| Kasa/Banka Finans Virmanı | Grup bazında iki hareket terslenir; hesaplar ve tutar düzeltip yeni virman |
| Çek/Senet (8 ekran) | Mevcut `SecuritiesCenter` vade/not düzenleme ve `ReplaceSecurity` mali düzeltme; bordro işlemi varsa önce yetkili bordro ters kaydı |
| Stok Düzeltme Fişi (`WarehouseSlip`) | Taslak yerinde; posted stok ters fişi + yeni fiş; önceki ortalama maliyet hareketi korunur |
| Transfer (`Transfer`) | Taslak yerinde; sevkte teslim/kalan iptali, tamamlandıktan sonra yeni transfer; stok hareketleri yerinde güncellenmez |
| Stok Sayımı (`StockCount`) | Taslak ve sayım/review satırları güncellenir; posted sonrası yeni sayım ile gerçek fiziksel fark doğrulanır |
| Taşıma Ambar Fişi (`TransportSlip`) | `draft` ve `ready` için doğrudan Düzenle; handed_over/completed belge bağımsız kopya oluşturma mevcut |
| Üretim Emri (`ProductionOrder`) | Taslakta doğrudan; onaylanan işte gerçekleşen üretimler kendilerine ait ters kayıtla, yeni emirle düzeltme |
| Proforma ve belge yazdırma snapshot'ları | Yeniden kaynak düzenleme/revizyon; eski PDF/belge görüntüsünün geriye dönük değiştirilmesi engellenir |
| E-ticaret kanal siparişleri / iadeleri | Harici kaynak eşitleme kayıtları; yetkili kaynak, manuel çekme ve stok senkronizasyon kuralları olmadan yerinde değiştirilemez |
| Belge/rapor şablonu revizyonları | `CreateDocumentTemplateRevision` ile ayrı versiyon; eski rapor ve çıktı denetlenebilir kalır |

## Düzeltilen somut eksikler

1. **İade:** `ReturnCenter` yalnız taslak oluştur/post ediyordu;
   taslak iade satırları düzenlenemiyordu. Yeni `UpdateReturnDraft`,
   yetki/dönem/optimistic version/kaynak fatura/miktar/currency ve
   sunucu KDV/iskonto hesaplayıcısı kontrolüyle taslağı değiştirir.
2. İade Blade içindeki `AppEnumsDocumentType` yanlış sınıf referansı
   `\App\Enums\DocumentType` ile düzeltildi.
3. **Cari borç/alacak fişi:** sadece `post` vardı. Geçmiş, cari yönü,
   ters kayıt onayı ve yeni fişe geçiş eklendi.
4. **Cari virman:** sadece Ters Kayıt vardı. Kaydı seç, alanları yükle,
   tersle, düzeltip yeni virman kaydet eklendi. Hesap bakiyesi yetki
   yoksa public Livewire önizlemesi üzerinden açıklanmaz.
5. **Finans operasyonları:** Manuel kasa/banka, iki taraflı virman,
   gider ve avans için düzeltme seçimi ve ters kayıt sonrası alanların
   korunması eklendi. Önceki hareketler silinmez.
6. **Livewire güvenliği:** düzeltme kaynak kimliği ve tamamlandı
   bayraklarına `#[Locked]` uygulandı; istemci bu kapıları atlayamaz.
   Yetki kontrolü ayrıca action içinde yapılır.
7. **Proforma:** kaynak teklif/sipariş bağlantısı ve yeni kaynak üzerinden
   düzenleme yolu eklendi; posted snapshot doğrudan değişmez.
8. **Mal Kabul/stok/üretim:** ilgili ekranlarda orijinal belgeyi bozmadan
   hangi işlemle düzeltileceğine dair görünür bağlantı ve açıklamalar.
9. **Finans liste görünürlüğü:** gelir/gider/avans ve manuel hareket
   kayıtları için `.view` yetki kontrolü korundu.
10. `MarsAllDocumentEditabilityAuditTest.php` kaynak kontratı manuel
    kalite workflow kapsamına alındı.

## Güvenlik/finans ilkeleri

- `Document::booted()` posted immutability **korunur**.
- İade taslaklarında `#[Locked]` source editing, saved id ve expected
  version; server-side `updateWithVersion` zorunlu.
- Kaynak belge satırının `source_line_id`, mal kabul ve stok
  reservations zinciri korunur.
- Ters kayıt öncesi düzeltme postu hem UI hem action kontrollüdür;
  `correctionReady` tarayıcıdan değiştirilemez.
- Yetki yoksa cari bakiye ve banka hareketleri public UI'dan ifşa
  edilmemelidir.
- `DocumentRelation` üzerinde reversal ilişkisi; çift tersleme
  ve eski belgeyi yeniden postlama idempotency ile sınırlıdır.
- E-belge entegrasyonu tasarlanmadı veya eklenmedi.

## Kalan test/kabul kapıları

1. `php -l` ile değişen tüm PHP dosyaları.
2. `php artisan view:cache` ve `route:list`.
3. Pint, PHPStan, yeni/eski bütün UI kontrat testleri.
4. İzole PostgreSQL üzerinde 18 belge durum matrisi,
   stok/cari/banka ters kayıtları ve maliyet tutarlılığı.
5. Kaynak yetkisi olmayan kullanıcılarda veri sızıntısı denemeleri.
6. İkinci tarayıcıda stale version, çift tıklama ve idempotency denemeleri.
7. Fresh UAT'de ekran kabulü ve DOM/ekran boyutu farkları.
8. Production Docker dağıtımı için ayrıca açık dağıtım ve
   backup/migration onayı.

**Statü:** kaynak taraması ve ilgili düzeltmeler. 18 belge türünün
gerçek veritabanında uçtan uca başarılı olduğu henüz ileri sürülmez.
