# Tüm belgeler — düzenleme yolu ve kaynak kod denetimi
Tarih: 2026-10-17 · İncelenen `main` başlangıç SHA: `185ebf884678c00f2c17aaa7164201f7cfb671cb`

## Kapsam ve yöntem

GitHub recursive tree: 2031 repository entry; `app/Actions` altında 193 PHP
action dosyası, 68 Livewire Blade view, 48 period migration.
`app/Enums/DocumentType.php` içindeki 18 **kalıcı belge türü** kapsamın
esas alındığı gerçek kaynaktır. Kaynak yapısı tam listelendi; kritik belge
editor, action, status guard, list/view ve route dosyaları içerik düzeyinde
örneklendi. Bütün 2031 dosyanın çalışma zamanı davranışını test etmiş değiliz.

### Belge matrisi (18/18)

| Belge türü | İşlem ekranı | Düzenleme politikası |
| --- | --- | --- |
| `quote` — Satış Teklifi | `QuoteEditor` | Taslak direkt, numaralı teklif revizyon |
| `sales_order` — Satış Siparişi | `SalesOrderEditor` | Taslak direkt; onaylı için bağımsız yeni taslak, önceki ticari işlem kalır |
| `dispatch` — Satış İrsaliyesi | `DispatchEditor` | Taslak direkt; kesinleşmişte ters kayıt ve yeni taslak |
| `sales_invoice` — Satış Faturası | `SalesInvoiceEditor` | Taslak direkt; kesinleşmişte ters kayıt ve yeni taslak |
| `proforma` | `ProformaDetail` | Snapshot salt-okunur; kaynak teklif/siparişten revizyon + yeni proforma |
| `collection` — Tahsilat | `CollectionForm` | Ters kayıt + yeni tahsilat, cari/kasa/banka dengesi korunur |
| `contact_debit_credit` — Cari Fiş | `ContactDebitCreditForm` | Ters kayıt + düzeltilmiş yeni fiş |
| `purchase_quote` — Alış Teklifi | `PurchaseQuoteEditor` | Taslak direkt; onaylı için bağımsız düzenleme taslağı |
| `purchase_order` — Satınalma Siparişi | `PurchaseOrderEditor` | Taslak direkt; onaylı/gönderilmiş için bağımsız yeni taslak |
| `purchase_dispatch` — Alış İrsaliyesi | `PurchaseDispatchEditor` | Bağlı mal kabul yoksa confirmed → draft kontrollü yeniden açma; aksi kapalı |
| `goods_receipt` — Mal Kabul | `GoodsReceiptEditor` | Taslak direkt; kesinleşmişte ters kayıt bağımlı alış faturasına göre kısıtlı |
| `supplier_invoice` — Alış Faturası | `SupplierInvoiceEditor` | Taslak direkt; kesinleşmişte ters kayıt + yeni taslak |
| `payment` — Tediye | `PaymentForm` | Ters kayıt + yeni tediye |
| `expense` — Gider | `FinanceOperationCenter` | Ters kayıt + yeni gider |
| `advance` — Avans | `FinanceOperationCenter` | Ters kayıt + yeni avans |
| `advance_return` — Avans İadesi | `FinanceOperationCenter` | Ters kayıt + yeni işlem |
| `sales_return` — Satış İadesi | `ReturnCenter` | Taslak doğrudan; kesinleşmişte ters kayıt, yeni iade |
| `purchase_return` — Alış İadesi | `ReturnCenter` | Taslak doğrudan; kesinleşmişte ters kayıt, yeni iade |

### Ayrı kayıt türleri / yardımcı operasyonlar

| Modül | Durum |
| --- | --- |
| Çek / senet | `SecuritiesCenter`: uygun evrakta vade/not değişir; parasal alanlarda `ReplaceSecurity` eski mali etkiyi tersler ve yenisini oluşturur. Bordro işlemi görmüş evrakta bu yol sınırlı |
| Cari virman | `ContactTransferForm`: seç → tersle → yeni virman |
| Nakit/banka manuel hareketi | `FinanceOperationCenter`: ayrı ters ve düzeltme |
| Nakit/banka virmanı | `FinanceOperationCenter`: transfer grup reversalı ve yeniden post |
| Ambar taşıma fişi | `TransportSlipEditor`: taslak/hazır aşamasında aynı fiş; kapalıysa yeni bağımsız kopya |
| Stok düzeltme fişi | `WarehouseSlipDetail`: taslak düzenleme; kesinleşmişte ters fiş + yeni fiş |
| Stok transferi | `TransferDetail`: taslak düzenleme; gönderilmiş/alınmış için kalan iptali/yeni transfer |
| Stok sayımı | `StockCountDetail`: taslak, sayım ve kontrol aşamalarında ilgili alanlar; kesinleşmişte yeni sayım |
| Üretim emri / tamamlanma | `ProductionOrderCenter`: taslak değişikliği; onaylı için yeni emir, tamamlanma düzeltmesi için ters kayıt |
| Üretim reçetesi | `RecipeCenter`: yeni revizyon |
| Proforma | Kalıcı belge; eski snapshot değiştirilmez |
| Banka ekstresi | İçe aktarma ve mutabakat ekranı; işlemi silip gizli mutasyon yapmak yerine mutabakatın kendi kuralları uygulanır |
| Stok hareketi / rezervasyon | Hareket geçmişi değişmez; kaynağa navigasyon ve kaynak belgenin kendi düzeltme ekranı |

## Bu denetimde giderilen somut eksik

1. Stok hareketi detayında `sourceUrl` yalnız `transfer`,
   `warehouse_slip`, `stock_count` türleri için kuruluyordu.
   Satış irsaliyesi, alış mal kabul, fatura, iade vb. belgelerin
   kaynak bağlantıları çözülemiyordu.
2. Rezervasyon detayında kaynak belge numarası pasif metindi.
3. `DocumentEditNavigation`, 18 belge tipi + 3 stok kaydı için
   **izinli** merkez veya detay rotalarını beyaz listeye alır.
   Erişim yetkisi olmayan bağlantı oluşturulmaz.
   Tarihsel finansal belgeler doğrudan sahte `/{id}` rotasına
   gönderilmez; gerçek düzeltme listesine yönlendirilir.
4. Yeni kaynak kontrat testi
   `tests/Unit/UI/MarsAllDocumentCorrectionNavigationTest.php`
   manuel V65 Source Quality workflow kapsamına alındı.

## Güvenlik politikası ve henüz kapanmayan sınırlamalar

- Her belge *iş akışı yoluyla* düzeltilebilir olması hedeflenir; her
  `posted` kaydın sütunlarını doğrudan güncellemek **yasak** kalmalıdır.
  `StockMovement::booted` update/delete engeller.
- Bordro işlemli çek/senet mali değişikliği mevcut `ReplaceSecurity`
  ile desteklenmez; önce bordro ilişkilerinin güvenli tasfiyesi gerekir.
- Tamamlanmış stok sayımının yerinde yeniden açılması yoktur;
  düzeltme ancak yeni sayım / sonraki stok düzeltme ile yapılır.
- Tamamlanmış stok transferi yerinde değiştirilmez; kalan iptali / yeni
  transfer ticari olarak farklı işlemlerdir.
- Kesinleşmiş proforma snapshot'ının üzerine yazılmaz; kaynağı revize
  edip yeni proforma oluşturulur.
- Kaynak onaylı siparişi yeni taslağa kopyalamak eski stok
  rezervasyonlarını veya üretim işlerini otomatik iptal **etmez**.
  Yeni taslak otomatik olarak öncekinin yerine geçmez.
- Kaynak belge üzerinden işlem yapmak için ilgili
  `.view`, `.update`, `.create`, `.cancel`
  izinleri durumuna göre ayrıca gereklidir.

## Kalite ve yayın sınırları

Bu çalışma **statik kaynak denetimi + yönlendirme düzeltmesi**dir.
QA iş akışını yalnız hazırladı; gerçek PHP lint, `php artisan view:cache`,
PHPStan, Pint, PostgreSQL işlem testleri, rollback doğrulaması ve
tarayıcı kabulü çalıştırılmış sayılmaz.
Production veya Fresh UAT dağıtımı yapılmadı.
