# V65 Cari Listesi — Onaylı iki görsel referansa uyarlama

**Tarih:** 13.10.2026

## Kabul edilen kapsam

- Mevcut üst uygulama çubuğu ve işlem aksiyonları korunur; yalnız Cari Listesi alt içerik alanı değişir.
- Cari Liste özet başlığında “Cari Listesi / Müşteri ve tedarikçi kartları” metni yer alır; iç başlığa ikinci bir Yeni Cari veya Excel/CSV butonu eklenmez.
- KPI kartları tek sırada altı başlıktır: Toplam Cari, Müşteri, Tedarikçi, Toplam Alacak, Toplam Borç, Aktif. Dar ekranlarda üç/iki sütuna uyarlanır.
- Müşteri/Tedarikçi sayıları `contact_categories` ilişkisinden hesaplanır, aynı Cari iki rolde bulunabilir; sayılar sahte referans değeri olarak girilmez.
- Cari açısından pozitif TRY net tutar **Borçlu/kırmızı**, negatif TRY net tutar **Alacaklı/yeşil** olarak sunulur. Şirketin muhasebe debit/credit yönleri değişmez. Birden fazla para birimi toplama işlemi yapılmaz.
- 11 sütun: Cari Kodu, Ünvan, Yetkili, Tip, İl/İlçe, Telefon, Vergi No, Bakiye, Risk Kullanımı, Son Hareket, Durum.
- Risk Kullanımı = carinin pozitif TRY net borcu / risk limiti * 100; risk limiti 0 veya boşsa “—”; net alacaklı cari için 0% gösterilir.
- Son Hareket tarihini gerçek `contact_transactions.transaction_date` üzerinden hesapla; fiş yoksa “—”.
- Cari kodu ve ünvan kart detayına tıklanabilir, satır Aç/Düzenle eylemleri bulunmaz.
- Checkbox'lar görünür kayıtlar için tek tıkla toplu seçim ve tekrar tıklamayla seçimi bırakma imkanı sunar.
- Filtre drawer, sayfalama, kolon gizleme ve dışa aktarma yetenekleri korunur.
- Diğer modüllerin genel DataTable davranışı ve Cari Detay formu değişmez.

## Değiştirilen dosyalar

1. `app/Livewire/Pages/Contacts/ContactList.php`: ilişkili kategorilere dayalı müşteri/tedarikçi istatistikleri; son hareket ve TRY net için ilgili kayıt başına ekstra sorgu gerektirmeyen selectSub; tablo sütunları; risk/ledger metinleri; satır checkbox seçimi.
2. `resources/views/livewire/components/data-table.blade.php`: yalnız Cari Listesi dalında altı kart, tarih/bakiye/etiket hücreleri, başlık checkbox ve başlık tekrarı kaldırma. Diğer liste ekranları korunur.
3. `resources/css/v65-shell.css`: yalnız `.v65-cari-list` kapsamlı KPI/tablo tasarım kuralları, erişilebilir bağlantı/renk/scroll ve duyarlı düzen.
4. `tests/Unit/UI/V65ContactCurrencyIsolationContractTest.php`: yeni Bakiye sütun adı beklentisi.
5. `tests/Unit/UI/V65ContactListReferenceParityTest.php`: yeni dört regresyon sözleşmesi ve biçimlendirme testi.

## Kontrol edilenler

- HTML kaynaklarında Cari Liste statik altı kart işaretçileri ve sıraları doğrulandı.
- SQL'de yalnız TRY hesabı ve per-record max transaction_date kullanımı doğrulandı.
- Kayıt bağlantıları, üst page-actions ve filtre çekmecesinin değişmediği kaynakta görüldü.
- Son yeni Blade parçasında eski hataya neden olan `@php(...)` kullanılmıyor.
- Değişiklikler yalnız ilgili ekran, ortak görünümde şartlı Cari Listesi dalı ve CSS kapsamıyla sınırlandırıldı.

**Henüz doğrulanmayanlar:** Gerçek PostgreSQL ile kategori sayıları, gerçek bakiye/risk ve son hareket verileri; tüm PHP/Blade çalışma zamanı/derleme, UI/E2E testleri ve 390/768/1024/1440/1920 px gerçek tarayıcı görünümü. Testler hazırlanmış ancak ayrı test onayı nedeniyle çalıştırılmamıştır. Fresh UAT web güncellemesi yapılmamıştır. PROD, PostgreSQL, Valkey veya demo verilerine müdahale edilmemiştir.
