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

## Şema

```php
Schema::connection('period')->create('contacts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
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
    $table->foreignId('source_company_id')->nullable()->constrained('companies');
    $table->unsignedBigInteger('source_record_id')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
    $table->unique(['company_id','code']);
    $table->index(['company_id','title']);
    $table->index(['company_id','tax_number']);
});
```

## Model
- `use BelongsToCompany, LogsActivity, SoftDeletes, HasAttachments;`
- `getTermDaysAttribute()` → boşsa `company->default_term_days`
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
- Silme yerine pasife alma önerilir; hareketi olan cari silinemez

## Kabul ölçütü
- Cari açılıyor, kod otomatik geliyor
- Aynı kod ikinci kez eklenemiyor
- Farklı şirkette aynı kod eklenebiliyor (izolasyon)
- `contacts.create` izni olmayan kullanıcı Yeni Cari düğmesini görmüyor
  ve doğrudan istek gönderse 403 alıyor

## İstem
> contacts tablosu için migration, Contact modeli, ContactList ve ContactForm
> Livewire bileşenlerini ve ContactPolicy'yi yaz. Şemayı birebir uygula.
> Model BelongsToCompany, LogsActivity, SoftDeletes ve HasAttachments
> trait'lerini kullansın. Liste ekranı DataTableComponent'ten türesin.
> Kod otomatik üretilsin (CR + 7 hane) ve kayıt sonrası salt okunur olsun.
