# G-803 — Production order

## Amaç

Reçete snapshot'ı taşıyan, kısmi completion destekleyen internal/subcontract production order akışını uygulamak.

## Önkoşul

G-801, G-802.

## Dokunulacak dosyalar

- ProductionOrder list/detail/create
- ConfirmProductionOrder
- sales order production-mode integration
- order tests

## Şema / Kod

Status:

- draft
- confirmed
- in_progress
- completed
- cancelled

Remaining:

```
planned - completed - cancelled
```

## Kurallar

- Confirm anında recipe/component/unit/conversion snapshot donar.
- source sales order opsiyonel.
- production-mode confirmed sales order draft production order açabilir.
- ayrı approval yok.
- açık order dönem devrinde taşınmaz.

## Kabul ölçütü

- Recipe revizyonundan bağımsız snapshot.
- Sales order source relation opsiyonel.
- Production-mode order draft production order oluşturuyor.
- Remaining doğru.
- Kalan iptal sonrası status doğru.
- Stale version reddediliyor.

## İstem

> K-135/K-141/K-142/K-158/K-159 production order akışını uygula.
