# G-104 — Cari kartı

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Amaç
Sistemin en çok kullanılan kartı. Müşteri ve tedarikçi **tek karttır**,
kategoriyle ayrılır, bakiye tektir.

## Önkoşul
G-0b2 (tablo bileşeni), G-0b3 (form bileşenleri)

## Dokunulacak dosyalar
- `database/migrations/xxxx_create_contacts_table.php`
- `app/Models/Contact.php`
- `app/Livewire/Pages/Contacts/ContactList.php` + blade
- `app/Livewire/Pages/Contacts/ContactForm.php` + blade
- `app/Policies/ContactPolicy.php`

## Şema / Kod
```php
Schema::connection('period')->create('contacts', function (Blueprint $table) {
    $table->id();

    $table->string('code', 30);
    $table->string('title');
    $table->string('type', 10)->default('legal');        // legal | real
    $table->string('tax_office')->nullable();
    $table->string('tax_number', 20)->nullable();
    $table->string('national_id', 11)->nullable();
    $table->text('address')->nullable();
    $table->string('city', 60)->nullable();
    $table->string('district', 60)->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('email')->nullable();
    $table->unsignedSmallInteger('term_days')->nullable();
    $table->decimal('risk_limit', 18, 4)->default(0);
    $table->decimal('discount_rate', 7, 4)->default(0);
    $table->foreignId('price_list_id')->nullable()->constrained('price_lists');
    $table->unsignedBigInteger('source_company_id')->nullable(); // cross-DB provenance; FK YOK
    $table->unsignedBigInteger('source_record_id')->nullable();
    $table->string('search_index')->nullable();
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();
    $table->unique('code');
    $table->index('title');
    $table->index('tax_number');
});
```

## Model
- `use LogsActivity, HasAttachments;` — `SoftDeletes` KULLANMA; kart `is_active=false` ile pasife alınır.
- `term_days` ham nullable değerdir. Şirket varsayılan vadesi period model accessor'ından çözülmez; fatura oluşturma akışındaki due-date resolver aktif şirketin Master `companies.default_term_days` değerine fallback yapar.
- İlişkiler: `categories()`, `addresses()`, `people()`, `banks()`
- `balance` şimdilik 0 döner (Faz 3'te `contact_transactions` gelecek)

## Liste ekranı

Kolonlar: Kod · Unvan (altında yetkili) · Kategori (rozet) · İl ·
Bakiye (sağa hizalı, renkli) · Risk durumu (rozet) · eylemler

Filtreler: kategori, il, aktif/pasif, limit aşanlar
Arama: kod, unvan, vergi no, telefon, e-posta
Eylemler: Yeni Cari · Dışa Aktar · satır: Düzenle, Cari Ekstre (Faz 3)

## Form ekranı

Bölüm **Firma / Ticari**: kod (salt okunur, otomatik), unvan, tip,
vergi dairesi/no, TC, kategori (çoklu), adres, il, ilçe, telefon, e-posta
Bölüm **Ticari koşullar**: vade günü (ipucu: boşsa şirket varsayılanı),
iskonto %, risk limiti (ipucu: aşımda uyarı verilir, engellenmez), durum

## Kurallar
- Kod otomatik: `CR` + 7 hane, kayıt sonrası değiştirilemez
- Vergi no girildiyse şirket içinde benzersiz olmalı (uyarı, engel değil)
- Cari fiziksel/soft delete edilmez; `is_active=false` ile pasife alınır. Kod tekrar kullanılamaz.


### Göreve özel kararlar
- Cari müşteri/tedarikçi tek karttır.
- Bakiye contacts üzerinde tutulmaz; `contact_transactions` Faz 3'te tek kaynak olur.
- Risk limiti blok değil uyarıdır.
- `source_company_id` Master companies'a FK değildir.


### Uygulama ayrıntıları
- Cari müşteri/tedarikçi tek `contacts` kartıdır; bakiye kart üzerinde saklanmaz.
- Risk limiti blok değil uyarıdır; gerçek bakiye Faz 3 `contact_transactions` toplamından gelir.
- `source_company_id + source_record_id` yalnız şirketler arası provenance'dır ve cross-DB FK değildir.
- Aynı şirket dönem devrinde contact ID ve code değişmeden taşınır; pasif kod başka karta verilemez.

## Kabul ölçütü
- Cari açılıyor, kod otomatik geliyor
- Aynı kod ikinci kez eklenemiyor
- Stale `version` ile ikinci eşzamanlı düzenleme reddediliyor
- Farklı şirkette aynı kod eklenebiliyor (izolasyon)
- `contacts.create` izni olmayan kullanıcı Yeni Cari düğmesini görmüyor
  ve doğrudan istek gönderse 403 alıyor


## İstem
> contacts tablosu için migration, Contact modeli, ContactList ve ContactForm
> Livewire bileşenlerini ve ContactPolicy'yi yaz. Şemayı birebir uygula.
> Model LogsActivity ve HasAttachments trait'lerini kullansın; SoftDeletes ekleme. Düzenleme `version` optimistic lock ile korunsun. Liste ekranı DataTableComponent'ten türesin.
> Kod otomatik üretilsin (CR + 7 hane) ve kayıt sonrası salt okunur olsun.
