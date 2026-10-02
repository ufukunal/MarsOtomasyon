# Fason üretim

## Model

K-147…K-150:

- fasoncu = normal supplier/contact,
- fasoncuya bağlı `subcontractor` location,
- şirket malzemesi kendi location'ından subcontractor location'a transfer edilir,
- mülkiyet şirkette kalır,
- subcontractor location normal satışta kullanılamaz.

## Fasona gönderim

K-155:

Gönderim kısmi olabilir.

Transfer:

- source normal location out,
- target subcontractor location in,
- transfer moving average değiştirmez.

## Fason completion

K-156:

Dönüş/completion kısmi olabilir.

Component consumption subcontractor location stoklarından yapılır.

Kalan component stok subcontractor location'da bekleyebilir.

## Fason fire

K-157:

İç üretimle aynı:

- component-level actual fire,
- subcontractor location'dan stock out,
- fire maliyeti mamule dahil.

## Hizmet faturası

K-151:

Normal purchase_invoice kullanılır.

- supplier = subcontractor contact,
- production order'a `subcontract_service_source` ilişkisi,
- cari/payment etkileri normal purchase_invoice/Faz 5 kurallarıyla.

Import file benzeri ayrı cari etkisi yoktur.

## Hizmet maliyeti

K-152:

Fason hizmet bedeli mamul production cost'a dahildir.

Completion anında bağlı posted hizmet faturası varsa uygun maliyet payı completion'a dahil edilir.

## Geç gelen hizmet faturası

K-153/K-154:

Completion hizmet faturası olmadan yapılabilir.

Sonradan gelen hizmet maliyeti:

- completion/original production quantity basis,
- `inventory_cost_adjustments`,
- reason=`subcontract_late_cost`,
- fiziksel stock quantity değişmez,
- mamul moving_average'a birim adjustment eklenir.

Geçmiş sales stock movement maliyetleri geriye dönük değiştirilmez.

## Dönem devri

Açık production order taşınmaz.

Subcontractor location'daki fiziksel stok yeni period opening stock'una location bazında taşınır.
