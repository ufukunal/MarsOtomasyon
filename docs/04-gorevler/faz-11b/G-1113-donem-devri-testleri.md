# G-1113 — Dönem devri bütünlük/eşzamanlılık testleri

## Amaç
CarryPeriod, preview, idempotency, integrity ve yeni fazlardan gelen carry kapsamını gerçek PostgreSQL üzerinde doğrulamak.

## Önkoşul
G-1110…G-1112.

## Dokunulacak dosyalar
- PeriodCarryIntegrationTest
- PeriodCarryFailureTest
- MultiPeriodCarryTest

## Şema / Kod
Yeni production şeması yok.

## Kurallar
- Master + source + target period DB.
- Gerçek PostgreSQL.
- Failure source period'u kapatmamalı.
- Partial target otomatik aktif olmamalı.

## Kabul ölçütü
- kart/stock_balance ID sürekliliği
- opening stock/cost
- cari/kasa/banka/security
- açık quarantine
- recipe/revision
- subcontractor location stock
- channel listing mapping
- geçmiş belge/hareket taşınmaması
- açık sales_order/purchase_order yalnız remaining snapshot carry
- carried sales_order/purchase_order target yılın kendi numara serisinden yeni numara alması; source numaranın `period_document_carries.source_document_number` provenance'ında korunması
- sales-order reservation location dağılımının yeniden kurulması
- cross-period order provenance
- carried açık channel sales_order için channel_order_snapshot provenance
- channel sync event/error history taşınmaması
- open production/in-transit blocker
- explicit-ID kopyalanan her tabloda sequence'in ilk auto-ID insert'ten önce MAX+1'e çekilmesi ve carry sonunda tekrar doğrulanması
- idempotency
- integrity:carry
- access override copy
- multi-period context restore

tam doğrulanır.

## İstem
> Faz 11b dönem devrini gerçek PostgreSQL multi-db testleriyle uçtan uca doğrula.
