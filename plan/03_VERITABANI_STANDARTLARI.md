# 03 — Veritabanı Standartları

## Motor
Production ve CI: **PostgreSQL 18**. SQLite/MySQL davranışına göre schema tasarlanmaz.

## Kimlikler
Internal primary key için Laravel uyumlu bigint veya UUID/ULID seçimi tablo ailesi içinde tutarlı olmalıdır. External/business belge numarası primary key değildir.

## Zaman
- timestamps timezone-aware tutulur
- business document date ayrıca alan olarak tutulur
- soft delete yalnız business anlamı uygunsa kullanılır
- finans/stok ledger kayıtları silinmez; reversal/correction ile düzeltilir

## Para
Para değerleri `numeric/decimal` tutulur; float kullanılmaz. Tutar, kur, fiyat ve oran precision'ları merkezi standartla tanımlanır.

## Miktar
Ürün miktarı decimal destekler. Birim hassasiyeti ürün/birim kuralına göre doğrulanır.

## Para birimi
Transaction satırında gerekirse:
- currency_code
- foreign_amount
- exchange_rate
- local_amount
snapshot olarak tutulur. Sonradan değişen kur geçmiş belgeyi değiştirmez.

## Belge numarası
Firma/şube/dönem/belge tipi kapsamındaki sequence atomik üretilir. Kullanıcı-visible belge no ile DB id ayrıdır.

## Immutable snapshot
Belge posting olduğunda değişebilir master verilerden gerekli alanlar snapshot olarak belgeye alınır: unvan, adres, vergi bilgisi, ürün adı/kodu, fiyat/KDV vb. Geçmiş belge master veri değişince anlamsızlaşmaz.

## Ledger tabloları
### `account_transactions`
Cari finans authority. Her kayıt source type/id ve reversal bağlantısı taşımalıdır.

### `stock_movements`
Stok authority. warehouse/location/product/quantity/direction/source alanlarıyla fiziksel hareketi temsil eder.

Bu ledger kayıtları normal CRUD ile update/delete edilmez.

## Progress alanları
Satış/satınalma satırlarında ordered/dispatched/received/invoiced/remaining gibi alanlar DB ve application invariant ile korunur. Negatif kalan miktar oluşamaz.

## Constraint ilkesi
Application validation tek başına yeterli değildir. Uygun yerlerde:
- NOT NULL
- CHECK
- UNIQUE
- FOREIGN KEY
- partial unique index
- exclusion/locking stratejileri
kullanılır.

## Index
Index gerçek sorguya göre eklenir. Yaygın filtre/sort/join kolonları ölçülür. Her foreign key için otomatik varsayım yapılmaz; query plan doğrulanır.

## Search index
PostgreSQL FTS ve `pg_trgm` indexleri ürün/cari/global search ihtiyaçlarına göre kurulur.

## JSONB
Sadece değişken provider payload, snapshot metadata veya gerçekten schemaless alanlarda. Core business alanları JSONB içine saklanmaz.

## Audit
Created/updated user gerektiği domainlerde ayrıca tutulur. Güvenlik/business audit ayrı append-only audit kaydı üretir.

## Migration
Migration geriye dönük güvenli olmalı; destructive değişiklikler `25_MIGRATION_VE_SCHEMA_DEGISIKLIK_PLAYBOOK.md` kurallarına uyar.
