# Health, log ve production güvenliği

## Health

Kontroller:
- HTTP/app
- Master DB
- active period DB
- Valkey
- queue lag
- failed jobs
- scheduler heartbeat
- disk free
- backup freshness
- storage writable
- integrity son durum

## Log

- unexpected exception → correlation id ile log
- DomainException → kullanıcıya göster, normalde error log gürültüsü yapma
- credential, API secret, password, encryption key loglanmaz
- sensitive full external payload loglanmaz

## Secret

- repository dışında
- environment/secret store
- channel credentials encrypted
- encryption key recovery plan backup runbook'un parçası

## Least privilege

- app web/worker user
- migration/deploy user
- backup user

gereken erişim kadar yetkilendirilir.

## Alert

En az:
- backup stale/failed
- disk kritik
- queue failed/lag
- scheduler heartbeat stale
- health failed
- integrity failed
- deploy failed

alarm üretir.

Business data otomatik onarılmaz.
