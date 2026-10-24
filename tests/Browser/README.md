# Mars V4 optional browser journeys

This is a separate test-only Playwright package, **not** an application dependency.
No old browser tests were restored.

Prerequisites: runner-local disposable Laravel application with **dedicated test** Master/Period databases on the existing PROD VM, accessed **only through the read-only-validated direct Tailscale connection** in `scripts/ci/test-v4-direct.sh`. CI must not install a PostgreSQL or Valkey server. The browser application must bind `127.0.0.1` (not Tailscale/public) and use the `mars_test` DB role and Valkey ACL. No production data or accounts. Browser tests do not create databases, migrate schemas or clean global data. The manual V4 workflow currently does not expose the browser mode; it requires an independently provisioned runner-local Laravel app and additional verification.

After *separate explicit execution approval*, prepare locally:

1. cd tests/Browser && npm install && npx playwright install chromium
2. Supply MARS_BROWSER_BASE_URL=http://127.0.0.1:8080
3. Verify direct Tailscale TEST database access and both dedicated test databases are ready before launching the runner-local Laravel app. Supply MARS_TEST_EXECUTION_APPROVED=MARS_V4_EXECUTION_APPROVED
4. Supply MARS_BROWSER_TESTS_APPROVED=I_APPROVE_LOCAL_BROWSER
5. npm test

An optional **local test-only** user ending in @invalid.test may be supplied
via MARS_BROWSER_TEST_EMAIL and MARS_BROWSER_TEST_PASSWORD for authenticated
navigation. Never provide production passwords.

The suite checks real browser guest redirects, login form shape, recovery link,
and local authenticated dashboard/period navigation. It does **not** claim full
authenticated business CRUD journey or broad accessibility coverage.

An optional authenticated read-only smoke sweep covers ten business modules.
It is **skipped** unless the actor above also has an isolated provisioned and
fully authorized company/period. Supply the local integer IDs through
MARS_BROWSER_TEST_COMPANY_ID and MARS_BROWSER_TEST_PERIOD_ID and set
MARS_BROWSER_TEST_PERIOD_READY=I_APPROVE_LOCAL_PERIOD_FIXTURES. The test
selects that company and period through the real Livewire selector and checks
that each module renders without HTTP 4xx/5xx errors. It performs no mutations.

## Additional explicit permission for browser writes

Product creation, contact creation and negative form validation tests are in `specs/isolated-product-contact-crud.spec.ts`. They are skipped unless `MARS_BROWSER_TEST_MUTATIONS_APPROVED=I_APPROVE_LOCAL_UI_WRITES`, all base browser approval gates and local company/period fixture IDs are present. For product creation also provide `MARS_BROWSER_TEST_PRODUCT_UNIT_ID` for a disposable local unit. All successful CRUD cases create records only in the dedicated remote PostgreSQL TEST database reached directly through Tailscale; they must never target production databases or accounts. Do not execute browser writes until dedicated fixture cleanup and test ACL restrictions have been independently verified. These test files have not been executed.

## V65 belge ekranları — yalnız okuma

`specs/v65-document-editor-readonly.spec.ts`, **ayrı ve açık test çalıştırma onayı** sonrasında, izole test şirketi/dönemi ve `@invalid.test` aktörüyle çalıştırılabilir. Daha önce tanımlı `MARS_TEST_EXECUTION_APPROVED`, `MARS_BROWSER_TESTS_APPROVED`, `MARS_BROWSER_TEST_PERIOD_READY` kapılarına ek olarak:

- `MARS_BROWSER_TEST_DOCUMENT_UI_APPROVED=I_APPROVE_LOCAL_DOCUMENT_UI` gereklidir.
- Aktör satış/alış belge görüntüleme ve yeni taslak editör sayfalarını açma yetkisine sahip olmalıdır.
- Altı yeni belge editörü `390 / 768 / 1024 / 1440 / 1920` px genişliklerinde yalnızca GET ve ekran denetimiyle kontrol edilir. Form kaydı, onay, kesinleştirme veya veri değişikliği yapılmaz.
- Mal kabul ekranı yalnızca izole test veritabanından önceden hazırlanmış `MARS_BROWSER_GOODS_RECEIPT_ID` ile açılır; aksi halde vaka atlanır.
- Bu testler, e-fatura/UBL veya yasal PDF çıktı doğrulaması değildir. Print/PDF ve iş akışı kabulü ayrıca yapılmalıdır.

**Not:** Bu test dosyası hazırlanmıştır; otomatik olarak çalıştırılmamıştır.


## Tasarım kalite paketi (24.10.2026)

Bu bölüm yalnız **izole test kullanıcısı** ve önceden tanımlanmış test şirketi/dönemi için geçerlidir. Mevcut MARS_TEST_EXECUTION_APPROVED, MARS_BROWSER_TESTS_APPROVED, MARS_BROWSER_TEST_PERIOD_READY güvenlik kapıları kaldırılmaz.

- specs/v65-shell-accessibility-phase1.spec.ts: ana içeriğe geçiş, ortak sekme klavye davranışı.
- specs/v65-theme-responsive-readonly.spec.ts: açık/orta/koyu görünüm seçimi ve 390, 768, 1024, 1440, 1920 px kabuk kontrolleri. Ek olarak MARS_BROWSER_TEST_DOCUMENT_UI_APPROVED=I_APPROVE_LOCAL_DOCUMENT_UI gerektirir.
- specs/v65-twenty-screen-readonly.spec.ts: onaylı 20 ekranın 390 ve 1440 px GET tabanlı kontrolleri. Ek olarak MARS_BROWSER_TEST_20_SCREEN_APPROVED=I_APPROVE_LOCAL_20_SCREEN_READONLY gerektirir. Alış irsaliyesi için MARS_BROWSER_PURCHASE_DISPATCH_ID, mal kabul için MARS_BROWSER_GOODS_RECEIPT_ID yalnızca test verisi ID'si olmalı; verilmezse ilgili vakalar atlanır. Aktörün 20 ekranın tamamına erişim izni bulunmalıdır.

Kaynak kodun her satırını tekrarlı olarak tarayan node scripts/ci/audit-v65-design.mjs komutu yalnız statik bulgu raporu üretir (artifacts/v65-design-source-audit.json). Bu tarama CSS tekrarları, satır içi stiller ve olaylar, form/ARIA kullanımı gibi gözden geçirilecek noktaları bulur; görsel veya erişilebilirlik başarısı iddia etmez.

**Hiçbir yeni test otomatik çalıştırılmaz.** Üretim, gerçek müşteri/finans verisi, erişilebilirlik kabulü, tüm temaların ekran görüntüsü ve yazıcı/barkod donanım testleri ayrı kullanıcı onayı gerektirir.
