# G-1102 — Production topoloji ve servis sınırları

## Amaç
K-021 VDS üzerinde web, PostgreSQL, Valkey, worker ve scheduler servislerini güvenli production topolojisinde kurmak.

## Önkoşul
G-021 altyapı kararı, K-236…K-238.

## Dokunulacak dosyalar
- deployment/server configs
- runtime env template
- process manager configs
- TLS/reverse-proxy config

## Şema / Kod
Yeni business şeması yok.

## Kurallar
- HTTPS.
- debug=false.
- public web root.
- worker/scheduler ayrı process.
- servisler ileride ayrı host'a taşınabilir sınırda.

## Kabul ölçütü
- app boot
- TLS
- DB/Valkey connectivity
- worker alive
- scheduler heartbeat
- secret public değil

## İstem
> Production servis topolojisini K-236…K-238'e göre kur.
