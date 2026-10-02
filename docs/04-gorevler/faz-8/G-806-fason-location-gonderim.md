# G-806 — Fason location ve malzeme gönderimi

## Amaç

Fasoncuyu contact + subcontractor location olarak modellemek ve şirket malzemesini kısmi transferlerle fason stoğa göndermek.

## Önkoşul

G-801, stok transfer altyapısı.

## Dokunulacak dosyalar

- subcontractor location form/policy
- subcontract transfer Action
- fason stok görünümü
- transfer tests

## Şema / Kod

Subcontractor location:

- location_type=subcontractor
- subcontractor_contact_id zorunlu

Gönderim normal stok transfer semantiğidir.

## Kurallar

- Mülkiyet şirkette kalır.
- Subcontractor location sales reservation/dispatch için hariç tutulur.
- Gönderim kısmi olabilir.
- Transfer moving average değiştirmez.
- source allow_negative_stock mevcut stok kuralına uyar.

## Kabul ölçütü

- Contact-location bağlantısı doğru.
- Transfer source out + subcontractor in.
- Normal satış bu location'ı seçemiyor.
- Kısmi çoklu gönderim çalışıyor.
- Transfer maliyeti korunuyor.

## İstem

> K-147…K-150/K-155 fason stok modelini mevcut location/transfer çekirdeğiyle uygula.
