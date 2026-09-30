# İsimlendirme

## Veritabanı
- Tablo: İngilizce, çoğul, snake_case → `contacts`, `stock_movements`
- Kolon: İngilizce, snake_case → `tax_number`, `created_at`
- Yabancı anahtar: `<tekil>_id` → `company_id`
- Boolean `is_` ön eki, tarih `_at` son eki
- Tutar `_amount`, oran `_rate`, miktar `quantity`

## Kod
- Model tekil PascalCase → `Contact`
- Action fiil + isim → `GenerateDocumentNumber`
- Livewire sayfa → `App\Livewire\Pages\Contacts\ContactList`

## Arayüz
Ekranda görünen her metin Türkçe, `lang/tr/` altında. Kod İngilizce.

## Sayısal tipler
| Tür | Tip |
|---|---|
| Tutar | `decimal(18,4)` |
| Miktar | `decimal(18,3)` |
| Oran | `decimal(7,4)` |
| Kur | `decimal(18,6)` |
