# G-300 — Faz 3 Satış özeti

## Amaç

Faz 3'te satış belge altyapısını kurmak: teklif → sipariş → rezervasyon → irsaliye → fatura → tahsilat zinciri. Bu fazda ortak `documents` altyapısı kurulur; Faz 4 alış belgeleri aynı çekirdeği kullanacaktır.

## Önkoşul

- Faz 0 / 0b tamamlanmış olmalı.
- Faz 1 kartları period DB'de hazır olmalı.
- Faz 2 stok, rezervasyon ve transfer altyapısı hazır olmalı.
- `RecordStockMovement`, `ReserveStock`, `ConsumeReservation`, `GenerateDocumentNumber`, `EnsurePeriodOpen`, idempotency ve optimistic lock kullanılabilir olmalı.

## Faz 3 kaynakları

### Veri modeli

- `docs/01-veri-modeli/30-documents.md`
- `31-document_lines.md`
- `32-contact_transactions.md`
- `33-document_relations.md`
- `34-cash-bank-minimum.md`

### İş kuralları

- `28-belge-hesaplama.md`
- `29-belge-yasam-dongusu.md`
- `30-kismi-islem.md`
- `31-cari-hareket-ve-yaslandirma.md`

### Ekranlar

- `teklif-detay.md`
- `satis-siparisi-detay.md`
- `irsaliye-detay.md`
- `satis-faturasi-detay.md`
- `tahsilat.md`
- `cari-yaslandirma.md`

## Görev sırası

| Görev | İçerik |
|---|---|
| G-301 | documents + document_lines + document_relations |
| G-302 | belge hesap motoru |
| G-303 | PostDocument |
| G-304 | teklif ve revizyon |
| G-305 | satış siparişi ve rezervasyon |
| G-306 | irsaliye / kısmi sevk |
| G-307 | satış faturası / kısmi fatura |
| G-308 | proforma |
| G-309 | tahsilat, cari hareket, minimum kasa/banka, yaşlandırma |
| G-310 | araçtan sıcak satış |
| G-311 | ters kayıt |
| G-312 | Faz 3 testleri |

## Faz 3 değişmezleri

- Period tablolarında `company_id` yok.
- Satış tarafında TRY kullanılır.
- Decimal aritmetik Money/BCMath.
- İskonto KDV'den önce.
- KDV oran grubu bazında hesaplanır.
- İrsaliye stok düşürür, cari etkilemez.
- Fatura cari borçlandırır; irsaliyeden geliyorsa stok tekrar düşmez.
- Doğrudan fatura stok + cari etkisi üretir.
- Cari bakiye yalnız `contact_transactions`.
- Zorunlu fatura-tahsilat settlement yok.
- Rezervasyon kullanılabilir stok kadar ve lokasyonlara bölünebilir.
- Posted belge immutable; ters kayıt gerekir.
- Her state-changing eylem idempotent.
- Gerçek PostgreSQL test zorunlu.

## Faz bitiş ölçütü

G-301…G-312 kabul ölçütleri gerçek PostgreSQL'de geçmeden Faz 3 uygulaması tamamlanmış sayılmaz.
