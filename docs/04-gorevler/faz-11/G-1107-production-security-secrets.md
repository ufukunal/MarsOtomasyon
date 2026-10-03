# G-1107 — Production security ve secret yönetimi

## Amaç
Secret, credential, DB erişimi, log redaction ve least-privilege production sınırlarını uygulamak.

## Önkoşul
G-1102.

## Dokunulacak dosyalar
- env/secret loading
- log redaction
- DB roles/grants runbook
- security tests

## Şema / Kod
Yeni business tablo yok.

## Kurallar
- secret repo dışında.
- channel credential encrypted.
- encryption key recovery prosedürü var.
- web/worker least privilege.
- deploy/backup credential ayrılabilir.
- sensitive payload log yok.

## Kabul ölçütü
- secret git/config artifact içinde yok.
- logs redact.
- app user gereksiz DB privilege taşımıyor.
- encryption key kayıp senaryosu runbook'ta.

## İstem
> K-247…K-249 production secret/log/DB privilege güvenliğini uygula.
